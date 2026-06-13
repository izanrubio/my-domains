<?php

namespace Tests\Feature\Commands;

use App\Models\Domain;
use App\Models\DnsRecord;
use App\Models\User;
use App\Services\WhoisService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncDomainsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->user->setSetting('cloudflare_api_token', 'test-cf-token');
    }

    private function fakeCfZones(array $zones): void
    {
        // dns_records must come first — zones* would otherwise match it too
        Http::fake([
            'api.cloudflare.com/client/v4/zones/*/dns_records*' => Http::response([
                'success' => true,
                'result' => [],
                'result_info' => ['total_pages' => 1],
            ]),
            'api.cloudflare.com/client/v4/zones*' => Http::response([
                'success' => true,
                'result' => $zones,
                'result_info' => ['total_pages' => 1],
            ]),
        ]);
    }

    public function test_creates_domains_and_dns_records(): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone-1/dns_records*' => Http::response([
                'success' => true,
                'result' => [
                    ['id' => 'rec-1', 'type' => 'A', 'name' => 'example.com', 'content' => '1.2.3.4', 'ttl' => 1, 'proxied' => true],
                ],
                'result_info' => ['total_pages' => 1],
            ]),
            'api.cloudflare.com/client/v4/zones*' => Http::response([
                'success' => true,
                'result' => [['id' => 'zone-1', 'name' => 'example.com', 'status' => 'active']],
                'result_info' => ['total_pages' => 1],
            ]),
        ]);

        $this->mock(WhoisService::class)->shouldReceive('getExpiryDate')->andReturn(null);

        $this->artisan('domains:sync')->assertSuccessful();

        $this->assertDatabaseHas('domains', ['name' => 'example.com', 'cloudflare_zone_id' => 'zone-1']);
        $this->assertDatabaseHas('dns_records', ['cloudflare_record_id' => 'rec-1', 'content' => '1.2.3.4']);
    }

    public function test_updates_existing_domain_status(): void
    {
        Domain::factory()->create(['name' => 'example.com', 'cloudflare_zone_id' => 'zone-1', 'status' => 'paused']);

        $this->fakeCfZones([['id' => 'zone-1', 'name' => 'example.com', 'status' => 'active']]);
        $this->mock(WhoisService::class)->shouldReceive('getExpiryDate')->andReturn(null);

        $this->artisan('domains:sync')->assertSuccessful();

        $this->assertSame('active', Domain::first()->status);
    }

    public function test_runs_whois_for_domain_without_expiry(): void
    {
        $this->fakeCfZones([['id' => 'zone-1', 'name' => 'example.com', 'status' => 'active']]);

        $expiry = Carbon::parse('2028-12-31');

        $this->mock(WhoisService::class)
            ->shouldReceive('getExpiryDate')
            ->with('example.com')
            ->once()
            ->andReturn($expiry);

        $this->artisan('domains:sync')->assertSuccessful();

        $domain = Domain::first();
        $this->assertSame('2028-12-31', $domain->expires_at->toDateString());
        $this->assertSame('whois', $domain->expiry_source);
    }

    public function test_skips_whois_for_domain_with_future_manual_expiry(): void
    {
        Domain::factory()->create([
            'cloudflare_zone_id' => 'zone-1',
            'name' => 'example.com',
            'expires_at' => now()->addYear(),
            'expiry_source' => 'manual',
        ]);

        $this->fakeCfZones([['id' => 'zone-1', 'name' => 'example.com', 'status' => 'active']]);

        $this->mock(WhoisService::class)
            ->shouldReceive('getExpiryDate')
            ->never();

        $this->artisan('domains:sync')->assertSuccessful();
    }

    public function test_fails_without_cloudflare_token(): void
    {
        $user = User::factory()->create(); // no CF token set, and no env token

        $this->artisan('domains:sync', ['--user' => $user->id])->assertFailed();
    }

    public function test_removes_stale_dns_records(): void
    {
        $domain = Domain::factory()->create(['cloudflare_zone_id' => 'zone-1', 'name' => 'example.com']);
        DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'cloudflare_record_id' => 'stale-rec',
        ]);

        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone-1/dns_records*' => Http::response([
                'success' => true,
                'result' => [],
                'result_info' => ['total_pages' => 1],
            ]),
            'api.cloudflare.com/client/v4/zones*' => Http::response([
                'success' => true,
                'result' => [['id' => 'zone-1', 'name' => 'example.com', 'status' => 'active']],
                'result_info' => ['total_pages' => 1],
            ]),
        ]);

        $this->mock(WhoisService::class)->shouldReceive('getExpiryDate')->andReturn(null);

        $this->artisan('domains:sync')->assertSuccessful();

        $this->assertDatabaseMissing('dns_records', ['cloudflare_record_id' => 'stale-rec']);
    }
}
