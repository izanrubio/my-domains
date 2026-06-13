<?php

namespace Tests\Unit\Scanning;

use App\Services\Scanning\Checks\BlacklistCheck;
use App\Services\Scanning\DohResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlacklistCheckTest extends TestCase
{
    private function makeCheck(): BlacklistCheck
    {
        return new BlacklistCheck(new DohResolver());
    }

    /**
     * Route fake by the `name` DoH query param.
     * $rules: array of [needle => response_body].
     * Matched by str_contains on the name param.
     * Default (no match): NXDOMAIN (clean).
     */
    private function fakeDoH(array $rules = [], array $domainARecord = []): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($rules, $domainARecord) {
            $qs = parse_url($request->url(), PHP_URL_QUERY) ?? '';
            parse_str($qs, $params);
            $name = $params['name'] ?? '';

            // Exact match for the domain's A record lookup
            if (isset($domainARecord[$name])) {
                return Http::response($domainARecord[$name]);
            }

            // Needle-based rules for DNSBL queries
            foreach ($rules as $needle => $body) {
                if (str_contains($name, $needle)) {
                    return Http::response($body);
                }
            }

            // Default: NXDOMAIN (not listed)
            return Http::response(['Status' => 3, 'AD' => false, 'Answer' => []]);
        });
    }

    private function aRecord(string $ip): array
    {
        return ['Status' => 0, 'AD' => false, 'Answer' => [['name' => 'example.com.', 'type' => 1, 'TTL' => 300, 'data' => $ip]]];
    }

    private function listed(): array
    {
        return ['Status' => 0, 'AD' => false, 'Answer' => [['name' => 'dummy.', 'type' => 1, 'TTL' => 300, 'data' => '127.0.0.4']]];
    }

    private function servfail(): array
    {
        return ['Status' => 2, 'AD' => false, 'Answer' => []];
    }

    private function nxdomain(): array
    {
        return ['Status' => 3, 'AD' => false, 'Answer' => []];
    }

    // -------------------------------------------------------------------------
    // ok — clean on all lists
    // -------------------------------------------------------------------------

    public function test_ok_domain_clean_on_all_lists(): void
    {
        // IP resolves, all DNSBL queries return NXDOMAIN (default)
        $this->fakeDoH(domainARecord: ['example.com' => $this->aRecord('1.2.3.4')]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('ok', $result->status);
        $this->assertSame(100, $result->score);

        $listStatuses = array_column($result->findings, 'status');
        $this->assertNotContains('listed', $listStatuses);
    }

    public function test_ok_when_some_lists_skipped_but_none_listed(): void
    {
        // Spamhaus returns SERVFAIL (typical for free-tier blocking), others are NXDOMAIN
        $this->fakeDoH(
            rules: [
                'zen.spamhaus.org' => $this->servfail(),
                'dbl.spamhaus.org' => $this->servfail(),
            ],
            domainARecord: ['example.com' => $this->aRecord('1.2.3.4')],
        );

        $result = $this->makeCheck()->run('example.com');

        // Some checked clean, none listed → ok (not skipped)
        $this->assertSame('ok', $result->status);

        $spamhausIp     = collect($result->findings)->firstWhere('list', 'zen.spamhaus.org');
        $spamcopIp      = collect($result->findings)->firstWhere('list', 'bl.spamcop.net');
        $this->assertSame('skipped', $spamhausIp['status']);
        $this->assertSame('clean', $spamcopIp['status']);
    }

    // -------------------------------------------------------------------------
    // fail — actual listing
    // -------------------------------------------------------------------------

    public function test_fail_when_ip_listed_on_spamhaus(): void
    {
        $this->fakeDoH(
            rules: ['zen.spamhaus.org' => $this->listed()],
            domainARecord: ['example.com' => $this->aRecord('1.2.3.4')],
        );

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('fail', $result->status);
        $this->assertSame(0, $result->score);
        $this->assertStringContainsString('zen.spamhaus.org', $result->summary);
    }

    public function test_fail_when_domain_listed_on_dbl(): void
    {
        $this->fakeDoH(
            rules: ['dbl.spamhaus.org' => $this->listed()],
            domainARecord: ['example.com' => $this->aRecord('1.2.3.4')],
        );

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('fail', $result->status);
        $this->assertStringContainsString('dbl.spamhaus.org', $result->summary);
    }

    // -------------------------------------------------------------------------
    // skipped — all lists unavailable
    // -------------------------------------------------------------------------

    public function test_skipped_when_all_doh_requests_fail(): void
    {
        // DoH itself is unreachable → DohResolver throws → queryList returns 'skipped'
        Http::fake(['*' => Http::response('', 503)]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('skipped', $result->status);
        $this->assertSame(0, $result->score);
    }

    public function test_skipped_when_all_lists_return_servfail(): void
    {
        // No IP (A record also SERVFAIL so resolveIp returns null)
        // Domain list also SERVFAIL → all skipped
        Http::fake(['*' => Http::response($this->servfail())]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('skipped', $result->status);
    }

    // -------------------------------------------------------------------------
    // SERVFAIL treated as skipped, NOT fail
    // -------------------------------------------------------------------------

    public function test_servfail_is_skipped_not_fail(): void
    {
        // SERVFAIL means Spamhaus refused the query (not a listing!)
        $this->fakeDoH(
            rules: ['zen.spamhaus.org' => $this->servfail()],
            domainARecord: ['example.com' => $this->aRecord('1.2.3.4')],
        );

        $result = $this->makeCheck()->run('example.com');

        $zenFinding = collect($result->findings)->firstWhere('list', 'zen.spamhaus.org');
        $this->assertSame('skipped', $zenFinding['status'], 'SERVFAIL must be skipped, not fail or listed');
        $this->assertNotSame('fail', $result->status);
    }

    // -------------------------------------------------------------------------
    // no A record — IP lists skipped, domain list still checked
    // -------------------------------------------------------------------------

    public function test_skips_ip_lists_when_domain_has_no_a_record(): void
    {
        // All A queries return NXDOMAIN → no IP → IP lists skipped
        // Domain list (dbl.spamhaus.org) returns NXDOMAIN (clean)
        $this->fakeDoH(); // all default NXDOMAIN

        $result = $this->makeCheck()->run('noa.example');

        $ipFindings = array_filter($result->findings, fn($f) => $f['type'] === 'ip');
        $this->assertEmpty($ipFindings, 'No IP findings when domain has no A record');

        $domainFindings = array_filter($result->findings, fn($f) => $f['type'] === 'domain');
        $this->assertNotEmpty($domainFindings);
    }

    // -------------------------------------------------------------------------
    // findings structure
    // -------------------------------------------------------------------------

    public function test_findings_include_list_query_and_status(): void
    {
        $this->fakeDoH(domainARecord: ['example.com' => $this->aRecord('1.2.3.4')]);

        $result = $this->makeCheck()->run('example.com');

        foreach ($result->findings as $finding) {
            $this->assertArrayHasKey('list', $finding);
            $this->assertArrayHasKey('query', $finding);
            $this->assertArrayHasKey('status', $finding);
            $this->assertContains($finding['status'], ['listed', 'clean', 'skipped']);
        }
    }

    public function test_ip_query_uses_reversed_octets(): void
    {
        $this->fakeDoH(domainARecord: ['example.com' => $this->aRecord('1.2.3.4')]);

        $result = $this->makeCheck()->run('example.com');

        $ipFinding = collect($result->findings)->firstWhere('type', 'ip');
        $this->assertStringStartsWith('4.3.2.1.', $ipFinding['query']);
    }
}
