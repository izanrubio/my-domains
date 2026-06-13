<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DnsRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DnsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->user->setSetting('cloudflare_api_token', 'test-cf-token');
        $this->domain = Domain::factory()->create(['cloudflare_zone_id' => 'zone-abc']);
    }

    private function fakeCfDnsRecords(array $records, string $zoneId = 'zone-abc'): void
    {
        Http::fake([
            "api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records*" => Http::response([
                'success' => true,
                'result' => $records,
                'result_info' => ['total_pages' => 1],
            ]),
        ]);
    }

    public function test_index_fetches_fresh_records_and_updates_local_cache(): void
    {
        $cfRecord = [
            'id' => 'cf-rec-1', 'type' => 'A', 'name' => 'example.com',
            'content' => '1.2.3.4', 'ttl' => 1, 'proxied' => true,
        ];
        $this->fakeCfDnsRecords([$cfRecord]);

        // Stale local record that should be removed
        DnsRecord::factory()->create([
            'domain_id' => $this->domain->id,
            'cloudflare_record_id' => 'stale-id',
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/domains/{$this->domain->id}/dns");
        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonFragment(['cloudflare_record_id' => 'cf-rec-1']);

        $this->assertDatabaseMissing('dns_records', ['cloudflare_record_id' => 'stale-id']);
        $this->assertDatabaseHas('dns_records', ['cloudflare_record_id' => 'cf-rec-1']);
    }

    public function test_store_creates_record_in_cloudflare_and_local_cache(): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone-abc/dns_records' => Http::response([
                'success' => true,
                'result' => [
                    'id' => 'new-rec', 'type' => 'A', 'name' => 'sub.example.com',
                    'content' => '5.6.7.8', 'ttl' => 3600, 'proxied' => false,
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/domains/{$this->domain->id}/dns", [
            'type' => 'A',
            'name' => 'sub.example.com',
            'content' => '5.6.7.8',
            'ttl' => 3600,
        ]);

        $response->assertCreated()->assertJsonFragment(['cloudflare_record_id' => 'new-rec']);
        $this->assertDatabaseHas('dns_records', ['cloudflare_record_id' => 'new-rec']);
    }

    public function test_update_updates_record_in_cloudflare_and_local_cache(): void
    {
        $local = DnsRecord::factory()->create([
            'domain_id' => $this->domain->id,
            'cloudflare_record_id' => 'rec-xyz',
            'content' => '1.1.1.1',
        ]);

        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone-abc/dns_records/rec-xyz' => Http::response([
                'success' => true,
                'result' => [
                    'id' => 'rec-xyz', 'type' => 'A', 'name' => $local->name,
                    'content' => '2.2.2.2', 'ttl' => 1, 'proxied' => false,
                ],
            ]),
        ]);

        $this->actingAs($this->user)->putJson(
            "/api/domains/{$this->domain->id}/dns/rec-xyz",
            ['type' => 'A', 'name' => $local->name, 'content' => '2.2.2.2']
        )->assertOk()->assertJsonFragment(['content' => '2.2.2.2']);

        $this->assertDatabaseHas('dns_records', ['cloudflare_record_id' => 'rec-xyz', 'content' => '2.2.2.2']);
    }

    public function test_destroy_deletes_from_cloudflare_and_local_cache(): void
    {
        DnsRecord::factory()->create([
            'domain_id' => $this->domain->id,
            'cloudflare_record_id' => 'del-rec',
        ]);

        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone-abc/dns_records/del-rec' => Http::response([
                'success' => true,
                'result' => ['id' => 'del-rec'],
            ]),
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/domains/{$this->domain->id}/dns/del-rec")
            ->assertNoContent();

        $this->assertDatabaseMissing('dns_records', ['cloudflare_record_id' => 'del-rec']);
    }

    public function test_store_rejects_invalid_ipv4_for_a_record(): void
    {
        $this->actingAs($this->user)->postJson("/api/domains/{$this->domain->id}/dns", [
            'type' => 'A',
            'name' => 'test.example.com',
            'content' => 'not-an-ip',
        ])->assertUnprocessable()->assertJsonValidationErrors('content');
    }

    public function test_store_rejects_invalid_ipv6_for_aaaa_record(): void
    {
        $this->actingAs($this->user)->postJson("/api/domains/{$this->domain->id}/dns", [
            'type' => 'AAAA',
            'name' => 'test.example.com',
            'content' => '1.2.3.4',
        ])->assertUnprocessable()->assertJsonValidationErrors('content');
    }

    public function test_store_accepts_valid_ipv4_for_a_record(): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone-abc/dns_records' => Http::response([
                'success' => true,
                'result' => [
                    'id' => 'ok-rec', 'type' => 'A', 'name' => 'a.example.com',
                    'content' => '192.168.1.1', 'ttl' => 1, 'proxied' => false,
                ],
            ]),
        ]);

        $this->actingAs($this->user)->postJson("/api/domains/{$this->domain->id}/dns", [
            'type' => 'A',
            'name' => 'a.example.com',
            'content' => '192.168.1.1',
        ])->assertCreated();
    }

    public function test_store_rejects_invalid_hostname_for_cname(): void
    {
        $this->actingAs($this->user)->postJson("/api/domains/{$this->domain->id}/dns", [
            'type' => 'CNAME',
            'name' => 'alias.example.com',
            'content' => 'not a valid hostname!',
        ])->assertUnprocessable()->assertJsonValidationErrors('content');
    }

    public function test_store_rejects_unsupported_dns_type(): void
    {
        $this->actingAs($this->user)->postJson("/api/domains/{$this->domain->id}/dns", [
            'type' => 'BOGUS',
            'name' => 'test',
            'content' => 'whatever',
        ])->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_requires_cf_token_to_be_set(): void
    {
        $user = User::factory()->create(); // no CF token
        $this->actingAs($user)->getJson("/api/domains/{$this->domain->id}/dns")
            ->assertStatus(422);
    }
}
