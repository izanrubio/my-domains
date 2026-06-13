<?php

namespace Tests\Unit\Scanning;

use App\Services\Scanning\Checks\ReachabilityCheck;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReachabilityCheckTest extends TestCase
{
    private function makeCheck(): ReachabilityCheck
    {
        return new ReachabilityCheck();
    }

    // -------------------------------------------------------------------------
    // ok — site responds 2xx
    // -------------------------------------------------------------------------

    public function test_ok_site_responds_200(): void
    {
        Http::fake(['*' => Http::response('<html>', 200)]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('ok', $result->status);
        $this->assertSame(100, $result->score);
        $this->assertStringContainsString('HTTP 200', $result->summary);
    }

    public function test_ok_detects_cloudflare_via_cf_ray_header(): void
    {
        Http::fake(['*' => Http::response('<html>', 200, ['CF-Ray' => '1234abcd-MAD'])]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('ok', $result->status);
        $this->assertSame('Cloudflare', $result->findings[0]['tech']);
        $this->assertStringContainsString('Cloudflare', $result->summary);
    }

    public function test_ok_detects_server_header(): void
    {
        Http::fake(['*' => Http::response('<html>', 200, ['Server' => 'nginx/1.24'])]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('nginx/1.24', $result->findings[0]['tech']);
    }

    public function test_ok_detects_x_powered_by(): void
    {
        Http::fake(['*' => Http::response('<html>', 200, ['X-Powered-By' => 'PHP/8.3'])]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('PHP/8.3', $result->findings[0]['tech']);
    }

    public function test_ok_no_tech_header_gives_null(): void
    {
        Http::fake(['*' => Http::response('<html>', 200, [])]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertNull($result->findings[0]['tech']);
    }

    // -------------------------------------------------------------------------
    // warning — 4xx
    // -------------------------------------------------------------------------

    public function test_warning_on_404(): void
    {
        Http::fake(['*' => Http::response('Not Found', 404)]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('warning', $result->status);
        $this->assertSame(30, $result->score);
        $this->assertStringContainsString('HTTP 404', $result->summary);
    }

    // -------------------------------------------------------------------------
    // fail — 5xx or connection error
    // -------------------------------------------------------------------------

    public function test_fail_on_500(): void
    {
        Http::fake(['*' => Http::response('Error', 500)]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('fail', $result->status);
        $this->assertSame(0, $result->score);
    }

    public function test_fail_on_connection_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused', 0);
        });

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('fail', $result->status);
        $this->assertSame(0, $result->score);
        $this->assertStringContainsString('unreachable', $result->summary);
        $this->assertEmpty($result->findings);
    }

    // -------------------------------------------------------------------------
    // findings structure
    // -------------------------------------------------------------------------

    public function test_findings_contain_status_code_response_time_and_url(): void
    {
        Http::fake(['*' => Http::response('<html>', 200)]);

        $result = $this->makeCheck()->run('example.com');

        $f = $result->findings[0];
        $this->assertArrayHasKey('status_code', $f);
        $this->assertArrayHasKey('response_time_ms', $f);
        $this->assertArrayHasKey('final_url', $f);
        $this->assertArrayHasKey('tech', $f);
        $this->assertSame(200, $f['status_code']);
        $this->assertIsInt($f['response_time_ms']);
        $this->assertSame('https://example.com', $f['final_url']);
    }

    public function test_cloudflare_detected_via_cf_cache_status(): void
    {
        Http::fake(['*' => Http::response('<html>', 200, ['cf-cache-status' => 'HIT'])]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('Cloudflare', $result->findings[0]['tech']);
    }
}
