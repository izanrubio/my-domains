<?php

namespace Tests\Unit\Scanning;

use App\Services\Scanning\Checks\DnsCheck;
use App\Services\Scanning\DohResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DnsCheckTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCheck(): DnsCheck
    {
        return new DnsCheck(new DohResolver());
    }

    /**
     * Register a Http::fake callback that routes by the `type` query param.
     * $overrides maps type string → response body array.
     * Any type not in $overrides gets Status=0, AD=false, Answer=[].
     */
    private function fakeDoH(array $overrides = []): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($overrides) {
            $query = parse_url($request->url(), PHP_URL_QUERY) ?? '';
            parse_str($query, $params);
            $type = $params['type'] ?? '';

            $body = $overrides[$type] ?? ['Status' => 0, 'AD' => false, 'Answer' => []];

            return Http::response($body);
        });
    }

    private function answer(string $data, int $rrType = 1): array
    {
        return ['name' => 'example.com.', 'type' => $rrType, 'TTL' => 300, 'data' => $data];
    }

    // -------------------------------------------------------------------------
    // ok — all records + DNSSEC (AD flag)
    // -------------------------------------------------------------------------

    public function test_ok_all_records_present_with_dnssec(): void
    {
        $this->fakeDoH([
            'A'      => ['Status' => 0, 'AD' => true,  'Answer' => [$this->answer('1.2.3.4', 1)]],
            'NS'     => ['Status' => 0, 'AD' => false, 'Answer' => [$this->answer('ns1.example.com.', 2)]],
            'MX'     => ['Status' => 0, 'AD' => false, 'Answer' => [$this->answer('10 mail.example.com.', 15)]],
            'DNSKEY' => ['Status' => 0, 'AD' => true,  'Answer' => [$this->answer('256 3 13 abc123', 48)]],
        ]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('ok', $result->status);
        $this->assertSame(100, $result->score);
        $this->assertSame('dns', $result->key);
        $this->assertSame(DnsCheck::WEIGHT, $result->weight);

        $dnssec = collect($result->findings)->firstWhere('type', 'DNSSEC');
        $this->assertTrue($dnssec['enabled']);
        $this->assertTrue($dnssec['ad_flag']);
        $this->assertTrue($dnssec['dnskey_found']);
    }

    public function test_dnssec_enabled_via_ad_flag_even_without_dnskey_record(): void
    {
        $this->fakeDoH([
            'A'  => ['Status' => 0, 'AD' => true, 'Answer' => [$this->answer('1.2.3.4', 1)]],
            'NS' => ['Status' => 0, 'AD' => false, 'Answer' => [$this->answer('ns1.example.com.', 2)]],
            'MX' => ['Status' => 0, 'AD' => false, 'Answer' => [$this->answer('10 mail.example.com.', 15)]],
            // DNSKEY returns empty (default) — AD flag alone should enable DNSSEC
        ]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('ok', $result->status);
        $dnssec = collect($result->findings)->firstWhere('type', 'DNSSEC');
        $this->assertTrue($dnssec['enabled']);
        $this->assertTrue($dnssec['ad_flag']);
        $this->assertFalse($dnssec['dnskey_found']);
    }

    // -------------------------------------------------------------------------
    // warning — resolves but missing DNSSEC (or other records)
    // -------------------------------------------------------------------------

    public function test_warning_no_dnssec(): void
    {
        $this->fakeDoH([
            'A'  => ['Status' => 0, 'AD' => false, 'Answer' => [$this->answer('1.2.3.4', 1)]],
            'NS' => ['Status' => 0, 'AD' => false, 'Answer' => [$this->answer('ns1.example.com.', 2)]],
            'MX' => ['Status' => 0, 'AD' => false, 'Answer' => [$this->answer('10 mail.example.com.', 15)]],
            // DNSKEY and all others return empty (default)
        ]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('warning', $result->status);
        // A(40) + NS(20) + MX(10) = 70
        $this->assertSame(70, $result->score);

        $dnssec = collect($result->findings)->firstWhere('type', 'DNSSEC');
        $this->assertFalse($dnssec['enabled']);
        $this->assertFalse($dnssec['ad_flag']);
    }

    public function test_warning_a_record_only_no_ns_no_mx_no_dnssec(): void
    {
        $this->fakeDoH([
            'A' => ['Status' => 0, 'AD' => false, 'Answer' => [$this->answer('1.2.3.4', 1)]],
        ]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('warning', $result->status);
        // A(40) only
        $this->assertSame(40, $result->score);
    }

    // -------------------------------------------------------------------------
    // fail — NXDOMAIN
    // -------------------------------------------------------------------------

    public function test_fail_nxdomain(): void
    {
        // All queries return NXDOMAIN; the check inspects only the A response first
        Http::fake(['*' => Http::response(['Status' => 3, 'AD' => false, 'Answer' => []])]);

        $result = $this->makeCheck()->run('nonexistent.example');

        $this->assertSame('fail', $result->status);
        $this->assertSame(0, $result->score);
        $this->assertStringContainsString('NXDOMAIN', $result->summary);
        $this->assertEmpty($result->findings);
    }

    public function test_fail_dns_server_error(): void
    {
        Http::fake(['*' => Http::response(['Status' => 2, 'AD' => false, 'Answer' => []])]);

        $result = $this->makeCheck()->run('broken.example');

        $this->assertSame('fail', $result->status);
        $this->assertStringContainsString('DNS error', $result->summary);
    }

    // -------------------------------------------------------------------------
    // skipped — resolver unreachable
    // -------------------------------------------------------------------------

    public function test_skipped_when_resolver_returns_http_error(): void
    {
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('skipped', $result->status);
        $this->assertSame(0, $result->score);
        $this->assertEmpty($result->findings);
        $this->assertStringContainsString('resolver', $result->summary);
    }

    public function test_skipped_preserves_key_label_weight(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('skipped', $result->status);
        $this->assertSame(DnsCheck::KEY, $result->key);
        $this->assertSame(DnsCheck::LABEL, $result->label);
        $this->assertSame(DnsCheck::WEIGHT, $result->weight);
    }

    // -------------------------------------------------------------------------
    // findings structure
    // -------------------------------------------------------------------------

    public function test_findings_contain_all_record_types(): void
    {
        $this->fakeDoH([
            'A'    => ['Status' => 0, 'AD' => false, 'Answer' => [$this->answer('1.2.3.4', 1)]],
            'AAAA' => ['Status' => 0, 'AD' => false, 'Answer' => [$this->answer('::1', 28)]],
            'TXT'  => ['Status' => 0, 'AD' => false, 'Answer' => [$this->answer('v=spf1 -all', 16)]],
            'SOA'  => ['Status' => 0, 'AD' => false, 'Answer' => [$this->answer('ns1.example.com. admin.example.com. 2024010101 3600 900 604800 300', 6)]],
        ]);

        $result = $this->makeCheck()->run('example.com');

        $types = array_column($result->findings, 'type');
        $this->assertContains('A', $types);
        $this->assertContains('AAAA', $types);
        $this->assertContains('TXT', $types);
        $this->assertContains('SOA', $types);
        $this->assertContains('DNSSEC', $types);
    }

    public function test_aaaa_only_domain_still_scores_address_points(): void
    {
        $this->fakeDoH([
            // A returns NOERROR but no records (IPv6-only domain)
            'A'    => ['Status' => 0, 'AD' => false, 'Answer' => []],
            'AAAA' => ['Status' => 0, 'AD' => false, 'Answer' => [$this->answer('2001:db8::1', 28)]],
            'NS'   => ['Status' => 0, 'AD' => false, 'Answer' => [$this->answer('ns1.example.com.', 2)]],
        ]);

        $result = $this->makeCheck()->run('ipv6only.example');

        // A(40) + NS(20) = 60; still warning, not fail
        $this->assertSame('warning', $result->status);
        $this->assertSame(60, $result->score);
    }
}
