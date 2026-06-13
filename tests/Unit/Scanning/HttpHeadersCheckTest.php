<?php

namespace Tests\Unit\Scanning;

use App\Services\Scanning\Checks\HttpHeadersCheck;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpHeadersCheckTest extends TestCase
{
    private function makeCheck(): HttpHeadersCheck
    {
        return new HttpHeadersCheck();
    }

    private function allSecurityHeaders(): array
    {
        return [
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Content-Security-Policy'   => "default-src 'self'",
            'X-Frame-Options'           => 'DENY',
            'X-Content-Type-Options'    => 'nosniff',
            'Referrer-Policy'           => 'strict-origin-when-cross-origin',
            'Permissions-Policy'        => 'geolocation=()',
        ];
    }

    private function fakeHttpsWithHeaders(array $responseHeaders, bool $httpRedirects = true): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($responseHeaders, $httpRedirects) {
            if (str_starts_with($request->url(), 'https://')) {
                return Http::response('<html>', 200, $responseHeaders);
            }
            // http:// request
            if ($httpRedirects) {
                return Http::response('', 301, ['Location' => 'https://example.com']);
            }
            return Http::response('<html>', 200);
        });
    }

    // -------------------------------------------------------------------------
    // ok — all headers + redirect
    // -------------------------------------------------------------------------

    public function test_ok_all_headers_and_redirect(): void
    {
        $this->fakeHttpsWithHeaders($this->allSecurityHeaders());

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('ok', $result->status);
        // redirect(15) + HSTS(20) + CSP(20) + X-Frame(15) + X-Content(10) + Referrer(10) + Permissions(10) = 100
        $this->assertSame(100, $result->score);

        $redirect = collect($result->findings)->firstWhere('check', 'https_redirect');
        $this->assertTrue($redirect['present']);
    }

    // -------------------------------------------------------------------------
    // warning — some headers missing
    // -------------------------------------------------------------------------

    public function test_warning_redirect_hsts_x_frame_only(): void
    {
        $this->fakeHttpsWithHeaders([
            'Strict-Transport-Security' => 'max-age=31536000',
            'X-Frame-Options'           => 'DENY',
        ]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('warning', $result->status);
        // redirect(15) + HSTS(20) + X-Frame(15) = 50
        $this->assertSame(50, $result->score);
    }

    public function test_warning_all_headers_but_no_redirect(): void
    {
        $this->fakeHttpsWithHeaders($this->allSecurityHeaders(), httpRedirects: false);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('warning', $result->status);
        // no redirect: 0 + HSTS(20) + CSP(20) + X-Frame(15) + X-Content(10) + Referrer(10) + Permissions(10) = 85
        $this->assertSame(85, $result->score);
    }

    // -------------------------------------------------------------------------
    // fail — too few headers
    // -------------------------------------------------------------------------

    public function test_fail_only_redirect_no_security_headers(): void
    {
        $this->fakeHttpsWithHeaders([]);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('fail', $result->status);
        // Computed score is 15 (redirect only) which is < 40 → fail.
        // CheckResult::fail() hardcodes score=0 (fail is binary: no partial credit).
        $this->assertSame(0, $result->score);
    }

    public function test_fail_no_redirect_no_headers(): void
    {
        $this->fakeHttpsWithHeaders([], httpRedirects: false);

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('fail', $result->status);
        $this->assertSame(0, $result->score);
    }

    // -------------------------------------------------------------------------
    // skipped
    // -------------------------------------------------------------------------

    public function test_skipped_when_https_request_throws(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused', 0);
        });

        $result = $this->makeCheck()->run('example.com');

        $this->assertSame('skipped', $result->status);
        $this->assertSame(0, $result->score);
        $this->assertEmpty($result->findings);
    }

    // -------------------------------------------------------------------------
    // findings structure
    // -------------------------------------------------------------------------

    public function test_findings_list_all_six_headers(): void
    {
        $this->fakeHttpsWithHeaders($this->allSecurityHeaders());

        $result = $this->makeCheck()->run('example.com');

        $headerNames = array_column(
            array_filter($result->findings, fn($f) => isset($f['header'])),
            'header',
        );

        $this->assertContains('Strict-Transport-Security', $headerNames);
        $this->assertContains('Content-Security-Policy', $headerNames);
        $this->assertContains('X-Frame-Options', $headerNames);
        $this->assertContains('X-Content-Type-Options', $headerNames);
        $this->assertContains('Referrer-Policy', $headerNames);
        $this->assertContains('Permissions-Policy', $headerNames);
    }

    public function test_header_finding_includes_value_when_present(): void
    {
        $this->fakeHttpsWithHeaders(['Strict-Transport-Security' => 'max-age=31536000']);

        $result = $this->makeCheck()->run('example.com');

        $hsts = collect($result->findings)->firstWhere('header', 'Strict-Transport-Security');
        $this->assertTrue($hsts['present']);
        $this->assertSame('max-age=31536000', $hsts['value']);
    }

    public function test_missing_header_finding_has_null_value(): void
    {
        $this->fakeHttpsWithHeaders([]);

        $result = $this->makeCheck()->run('example.com');

        $csp = collect($result->findings)->firstWhere('header', 'Content-Security-Policy');
        $this->assertFalse($csp['present']);
        $this->assertNull($csp['value']);
    }

    public function test_redirect_finding_includes_chain(): void
    {
        $this->fakeHttpsWithHeaders([], httpRedirects: true);

        $result = $this->makeCheck()->run('example.com');

        $redirect = collect($result->findings)->firstWhere('check', 'https_redirect');
        $this->assertTrue($redirect['present']);
        $this->assertContains('http://example.com', $redirect['redirect_chain']);
        $this->assertContains('https://example.com', $redirect['redirect_chain']);
    }
}
