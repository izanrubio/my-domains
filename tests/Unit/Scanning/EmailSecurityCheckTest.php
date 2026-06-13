<?php

namespace Tests\Unit\Scanning;

use App\Services\Scanning\Checks\EmailSecurityCheck;
use App\Services\Scanning\DohResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmailSecurityCheckTest extends TestCase
{
    private function makeCheck(): EmailSecurityCheck
    {
        return new EmailSecurityCheck(new DohResolver());
    }

    /**
     * Route Http::fake by name:type key.
     * Any key not in $overrides returns an empty NOERROR response.
     */
    private function fakeDoH(array $overrides = []): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($overrides) {
            $qs = parse_url($request->url(), PHP_URL_QUERY) ?? '';
            parse_str($qs, $params);
            $key = ($params['name'] ?? '') . ':' . ($params['type'] ?? '');

            $body = $overrides[$key] ?? ['Status' => 0, 'AD' => false, 'Answer' => []];

            return Http::response($body);
        });
    }

    private function txt(string $data): array
    {
        return ['Status' => 0, 'AD' => false, 'Answer' => [['name' => 'example.com.', 'type' => 16, 'TTL' => 300, 'data' => $data]]];
    }

    private function mx(string $data): array
    {
        return ['Status' => 0, 'AD' => false, 'Answer' => [['name' => 'example.com.', 'type' => 15, 'TTL' => 300, 'data' => $data]]];
    }

    // -------------------------------------------------------------------------
    // ok — full protection
    // -------------------------------------------------------------------------

    public function test_ok_spf_dmarc_reject_dkim_mx(): void
    {
        $this->fakeDoH([
            'example.com:TXT'                        => $this->txt('v=spf1 include:_spf.google.com -all'),
            '_dmarc.example.com:TXT'                 => $this->txt('v=DMARC1; p=reject; rua=mailto:dmarc@example.com'),
            'example.com:MX'                         => $this->mx('10 mail.example.com.'),
            'google._domainkey.example.com:TXT'      => $this->txt('v=DKIM1; k=rsa; p=abc123'),
        ]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('ok', $result->status);
        // SPF(25) + DMARC/reject(35) + DKIM(25) + MX(15) = 100
        $this->assertSame(100, $result->score);
    }

    public function test_ok_with_dmarc_quarantine_and_dkim(): void
    {
        $this->fakeDoH([
            'example.com:TXT'                   => $this->txt('v=spf1 -all'),
            '_dmarc.example.com:TXT'            => $this->txt('v=DMARC1; p=quarantine'),
            'example.com:MX'                    => $this->mx('10 mail.example.com.'),
            'default._domainkey.example.com:TXT' => $this->txt('v=DKIM1; k=rsa; p=xyz'),
        ]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('ok', $result->status);
        // SPF(25) + DMARC/quarantine(25) + DKIM(25) + MX(15) = 90
        $this->assertSame(100, $result->score); // ok() hardcodes 100
    }

    // -------------------------------------------------------------------------
    // warning — partial protection
    // -------------------------------------------------------------------------

    public function test_warning_spf_and_mx_only(): void
    {
        $this->fakeDoH([
            'example.com:TXT' => $this->txt('v=spf1 -all'),
            'example.com:MX'  => $this->mx('10 mail.example.com.'),
        ]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('warning', $result->status);
        // SPF(25) + MX(15) = 40
        $this->assertSame(40, $result->score);
    }

    public function test_warning_dmarc_none_policy_gives_partial_credit(): void
    {
        $this->fakeDoH([
            'example.com:TXT'        => $this->txt('v=spf1 -all'),
            '_dmarc.example.com:TXT' => $this->txt('v=DMARC1; p=none'),
        ]);

        $result = $this->makeCheck()->run('example.com');

        // SPF(25) + DMARC/none(15) = 40 → warning
        $this->assertSame('warning', $result->status);
        $this->assertSame(40, $result->score);

        $dmarc = collect($result->findings)->firstWhere('type', 'DMARC');
        $this->assertSame('none', $dmarc['policy']);
    }

    // -------------------------------------------------------------------------
    // fail — no protection
    // -------------------------------------------------------------------------

    public function test_fail_no_email_records(): void
    {
        // All lookups return empty (default fakeDoH)
        $this->fakeDoH();

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('fail', $result->status);
        $this->assertSame(0, $result->score);
    }

    // -------------------------------------------------------------------------
    // skipped
    // -------------------------------------------------------------------------

    public function test_skipped_when_resolver_fails(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('skipped', $result->status);
        $this->assertSame(0, $result->score);
        $this->assertEmpty($result->findings);
    }

    // -------------------------------------------------------------------------
    // DKIM selector probing
    // -------------------------------------------------------------------------

    public function test_dkim_reports_which_selectors_were_found(): void
    {
        $this->fakeDoH([
            'selector1._domainkey.example.com:TXT' => $this->txt('v=DKIM1; k=rsa; p=abc'),
            'mail._domainkey.example.com:TXT'      => $this->txt('v=DKIM1; k=rsa; p=xyz'),
        ]);

        $result = $this->makeCheck()->run('example.com');

        $dkim = collect($result->findings)->firstWhere('type', 'DKIM');
        $this->assertContains('selector1', $dkim['selectors_found']);
        $this->assertContains('mail', $dkim['selectors_found']);
        $this->assertNotContains('google', $dkim['selectors_found']);
        $this->assertNotEmpty($dkim['selectors_checked']);
    }

    public function test_dkim_no_selectors_found_reports_empty(): void
    {
        $this->fakeDoH();

        $result = $this->makeCheck()->run('example.com');

        $dkim = collect($result->findings)->firstWhere('type', 'DKIM');
        $this->assertSame([], $dkim['selectors_found']);
    }

    // -------------------------------------------------------------------------
    // findings structure
    // -------------------------------------------------------------------------

    public function test_findings_contain_all_four_types(): void
    {
        $this->fakeDoH([
            'example.com:TXT'        => $this->txt('v=spf1 -all'),
            '_dmarc.example.com:TXT' => $this->txt('v=DMARC1; p=reject'),
            'example.com:MX'         => $this->mx('10 mail.example.com.'),
        ]);

        $result = $this->makeCheck()->run('example.com');

        $types = array_column($result->findings, 'type');
        $this->assertContains('SPF', $types);
        $this->assertContains('DMARC', $types);
        $this->assertContains('DKIM', $types);
        $this->assertContains('MX', $types);
    }

    public function test_mx_records_listed_in_findings(): void
    {
        $this->fakeDoH([
            'example.com:MX' => $this->mx('10 mail.example.com.'),
        ]);

        $result = $this->makeCheck()->run('example.com');

        $mx = collect($result->findings)->firstWhere('type', 'MX');
        $this->assertNotEmpty($mx['records']);
        $this->assertStringContainsString('mail.example.com', $mx['records'][0]);
    }
}
