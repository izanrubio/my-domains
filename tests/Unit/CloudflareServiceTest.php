<?php

namespace Tests\Unit;

use App\Exceptions\Cloudflare\InvalidTokenException;
use App\Exceptions\Cloudflare\RateLimitException;
use App\Exceptions\Cloudflare\CloudflareException;
use App\Services\CloudflareService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareServiceTest extends TestCase
{
    private CloudflareService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CloudflareService('test-token');
    }

    public function test_list_zones_returns_all_pages(): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones*' => Http::sequence()
                ->push([
                    'success' => true,
                    'result' => [['id' => 'zone1', 'name' => 'example.com']],
                    'result_info' => ['total_pages' => 2, 'page' => 1],
                ])
                ->push([
                    'success' => true,
                    'result' => [['id' => 'zone2', 'name' => 'example.org']],
                    'result_info' => ['total_pages' => 2, 'page' => 2],
                ]),
        ]);

        $zones = $this->service->listZones();

        $this->assertCount(2, $zones);
        $this->assertSame('zone1', $zones[0]['id']);
        $this->assertSame('zone2', $zones[1]['id']);
    }

    public function test_list_zones_throws_invalid_token_on_401(): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones*' => Http::response([], 401),
        ]);

        $this->expectException(InvalidTokenException::class);
        $this->service->listZones();
    }

    public function test_list_zones_throws_rate_limit_on_429(): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones*' => Http::response([], 429),
        ]);

        $this->expectException(RateLimitException::class);
        $this->service->listZones();
    }

    public function test_list_zones_throws_on_api_error(): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones*' => Http::response([
                'success' => false,
                'errors' => [['message' => 'Something went wrong']],
            ], 200),
        ]);

        $this->expectException(CloudflareException::class);
        $this->service->listZones();
    }

    public function test_list_dns_records_returns_all_pages(): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone1/dns_records*' => Http::sequence()
                ->push([
                    'success' => true,
                    'result' => [['id' => 'rec1', 'type' => 'A', 'name' => 'example.com', 'content' => '1.2.3.4', 'ttl' => 1, 'proxied' => true]],
                    'result_info' => ['total_pages' => 1],
                ]),
        ]);

        $records = $this->service->listDnsRecords('zone1');

        $this->assertCount(1, $records);
        $this->assertSame('rec1', $records[0]['id']);
    }

    public function test_create_dns_record(): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone1/dns_records' => Http::response([
                'success' => true,
                'result' => ['id' => 'new-rec', 'type' => 'A'],
            ], 200),
        ]);

        $result = $this->service->createDnsRecord('zone1', ['type' => 'A', 'name' => 'test', 'content' => '1.2.3.4']);

        $this->assertSame('new-rec', $result['id']);
    }

    public function test_delete_dns_record_succeeds(): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone1/dns_records/rec1' => Http::response([
                'success' => true,
                'result' => ['id' => 'rec1'],
            ], 200),
        ]);

        $this->service->deleteDnsRecord('zone1', 'rec1');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_throws_403_as_invalid_token(): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones*' => Http::response([], 403),
        ]);

        $this->expectException(InvalidTokenException::class);
        $this->service->listZones();
    }
}
