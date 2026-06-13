<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DnsRecord;
use App\Models\User;
use App\Services\WhoisService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_domains_with_days_until_expiry(): void
    {
        $user = User::factory()->create();
        Domain::factory()->expiringSoon(10)->create(['name' => 'example.com']);
        Domain::factory()->create(['name' => 'other.com', 'expires_at' => null]);

        $response = $this->actingAs($user)->getJson('/api/domains');
        $response->assertOk()->assertJsonCount(2);

        $hit = collect($response->json())->firstWhere('name', 'example.com');
        $this->assertNotNull($hit['days_until_expiry']);
        $this->assertGreaterThanOrEqual(9, $hit['days_until_expiry']);
    }

    public function test_show_returns_domain_with_dns_records(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create();
        DnsRecord::factory()->create(['domain_id' => $domain->id]);

        $response = $this->actingAs($user)->getJson("/api/domains/{$domain->id}");
        $response->assertOk()
            ->assertJsonFragment(['name' => $domain->name])
            ->assertJsonStructure(['dns_records']);
    }

    public function test_update_domain_fields(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create();

        $this->actingAs($user)->putJson("/api/domains/{$domain->id}", [
            'notes' => 'Updated notes',
            'auto_renew' => true,
            'expires_at' => '2027-01-15',
        ])->assertOk();

        $fresh = $domain->fresh();
        $this->assertSame('Updated notes', $fresh->notes);
        $this->assertTrue($fresh->auto_renew);
        $this->assertSame('2027-01-15', $fresh->expires_at->toDateString());
        $this->assertSame('manual', $fresh->expiry_source);
    }

    public function test_update_validates_expiry_source(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create();

        $this->actingAs($user)->putJson("/api/domains/{$domain->id}", [
            'expiry_source' => 'invalid',
        ])->assertUnprocessable()->assertJsonValidationErrors('expiry_source');
    }

    public function test_sync_calls_domains_sync_command(): void
    {
        $user = User::factory()->create();
        $user->setSetting('cloudflare_api_token', 'test-token');

        Http::fake([
            'api.cloudflare.com/client/v4/zones*' => Http::response([
                'success' => true,
                'result' => [],
                'result_info' => ['total_pages' => 1],
            ]),
        ]);

        $this->mock(WhoisService::class)
            ->shouldReceive('getExpiryDate')
            ->andReturn(null);

        $this->actingAs($user)->postJson('/api/domains/sync')
            ->assertOk()
            ->assertJson(['message' => 'Sync completed.']);
    }

    public function test_whois_updates_domain_expiry(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create(['expires_at' => null]);
        $expiry = Carbon::parse('2028-06-01');

        $this->mock(WhoisService::class)
            ->shouldReceive('getExpiryDate')
            ->with($domain->name)
            ->andReturn($expiry);

        $this->actingAs($user)->postJson("/api/domains/{$domain->id}/whois")
            ->assertOk()
            ->assertJsonFragment(['expiry_source' => 'whois']);

        $this->assertSame('2028-06-01', $domain->fresh()->expires_at->toDateString());
    }

    public function test_whois_returns_422_when_not_found(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create();

        $this->mock(WhoisService::class)
            ->shouldReceive('getExpiryDate')
            ->andReturn(null);

        $this->actingAs($user)->postJson("/api/domains/{$domain->id}/whois")
            ->assertStatus(422);
    }
}
