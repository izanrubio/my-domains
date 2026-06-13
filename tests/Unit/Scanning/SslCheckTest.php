<?php

namespace Tests\Unit\Scanning;

use App\Services\Scanning\Checks\SslCheck;
use App\Services\Scanning\Contracts\CertFetcherInterface;
use Tests\TestCase;

class SslCheckTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCheck(?array $certData, bool $throws = false): SslCheck
    {
        $fetcher = new class($certData, $throws) implements CertFetcherInterface {
            public function __construct(
                private readonly ?array $certData,
                private readonly bool   $throws,
            ) {}

            public function fetch(string $host): ?array
            {
                if ($this->throws) {
                    throw new \RuntimeException('Unexpected cert-fetch error');
                }
                return $this->certData;
            }
        };

        return new SslCheck($fetcher);
    }

    /**
     * Build a fixture that looks like openssl_x509_parse() output.
     * Pass a negative $daysFromNow to simulate an expired cert.
     */
    private function fixtureCert(int $daysFromNow = 365): array
    {
        return [
            'validFrom_time_t' => now()->subYear()->startOfDay()->timestamp,
            'validTo_time_t'   => now()->addDays($daysFromNow)->startOfDay()->timestamp,
            'issuer'           => ['O' => "Let's Encrypt", 'CN' => 'R3'],
            'subject'          => ['CN' => 'example.com'],
            'extensions'       => ['subjectAltName' => 'DNS:example.com, DNS:www.example.com'],
        ];
    }

    // -------------------------------------------------------------------------
    // ok
    // -------------------------------------------------------------------------

    public function test_ok_cert_valid_with_plenty_of_time(): void
    {
        $result = $this->makeCheck($this->fixtureCert(365))->run('example.com');

        $this->assertSame('ok', $result->status);
        $this->assertSame(100, $result->score);
        $this->assertSame(SslCheck::WEIGHT, $result->weight);
        $this->assertStringContainsString("Let's Encrypt", $result->summary);
    }

    // -------------------------------------------------------------------------
    // warning
    // -------------------------------------------------------------------------

    public function test_warning_cert_expires_within_30_days(): void
    {
        $result = $this->makeCheck($this->fixtureCert(25))->run('example.com');

        $this->assertSame('warning', $result->status);
        $this->assertSame(50, $result->score);
        $this->assertStringContainsString('25 days', $result->summary);
    }

    public function test_warning_cert_expires_within_14_days(): void
    {
        $result = $this->makeCheck($this->fixtureCert(7))->run('example.com');

        $this->assertSame('warning', $result->status);
        $this->assertSame(20, $result->score);
        $this->assertStringContainsString('urgently', $result->summary);
    }

    // -------------------------------------------------------------------------
    // fail
    // -------------------------------------------------------------------------

    public function test_fail_cert_expired(): void
    {
        $result = $this->makeCheck($this->fixtureCert(-5))->run('example.com');

        $this->assertSame('fail', $result->status);
        $this->assertSame(0, $result->score);
        $this->assertTrue($result->findings[0]['expired']);
    }

    public function test_fail_no_https_fetcher_returns_null(): void
    {
        $result = $this->makeCheck(null)->run('example.com');

        $this->assertSame('fail', $result->status);
        $this->assertSame(0, $result->score);
        $this->assertStringContainsString('no HTTPS', $result->summary);
        $this->assertEmpty($result->findings);
    }

    // -------------------------------------------------------------------------
    // skipped
    // -------------------------------------------------------------------------

    public function test_skipped_on_unexpected_fetcher_exception(): void
    {
        $result = $this->makeCheck(null, throws: true)->run('example.com');

        $this->assertSame('skipped', $result->status);
        $this->assertSame(0, $result->score);
        $this->assertSame(SslCheck::KEY, $result->key);
        $this->assertSame(SslCheck::LABEL, $result->label);
        $this->assertSame(SslCheck::WEIGHT, $result->weight);
        $this->assertEmpty($result->findings);
    }

    // -------------------------------------------------------------------------
    // findings structure / key names
    // -------------------------------------------------------------------------

    public function test_finding_key_is_days_until_cert_expiry_not_domain_expiry(): void
    {
        // The field name must be 'days_until_cert_expiry' to make clear
        // this is cert expiry, NOT domain registration expiry.
        $result = $this->makeCheck($this->fixtureCert(365))->run('example.com');

        $this->assertArrayHasKey('days_until_cert_expiry', $result->findings[0]);
        $this->assertArrayNotHasKey('days_until_expiry', $result->findings[0]);
    }

    public function test_findings_include_valid_dates_and_issuer(): void
    {
        $result = $this->makeCheck($this->fixtureCert(365))->run('example.com');

        $f = $result->findings[0];
        $this->assertSame("Let's Encrypt", $f['issuer']);
        $this->assertSame('example.com', $f['subject']);
        $this->assertNotEmpty($f['valid_from']);
        $this->assertNotEmpty($f['valid_to']);
        $this->assertFalse($f['expired']);
    }

    public function test_parses_sans_from_subject_alt_name(): void
    {
        $result = $this->makeCheck($this->fixtureCert(365))->run('example.com');

        $sans = $result->findings[0]['sans'];
        $this->assertContains('example.com', $sans);
        $this->assertContains('www.example.com', $sans);
    }

    public function test_empty_san_string_returns_empty_sans(): void
    {
        $cert = $this->fixtureCert(365);
        $cert['extensions']['subjectAltName'] = '';

        $result = $this->makeCheck($cert)->run('example.com');

        $this->assertSame([], $result->findings[0]['sans']);
    }

    public function test_cert_at_exactly_30_days_is_warning(): void
    {
        $result = $this->makeCheck($this->fixtureCert(30))->run('example.com');
        $this->assertSame('warning', $result->status);
        $this->assertSame(50, $result->score);
    }

    public function test_cert_at_exactly_14_days_is_urgent_warning(): void
    {
        $result = $this->makeCheck($this->fixtureCert(14))->run('example.com');
        $this->assertSame('warning', $result->status);
        $this->assertSame(20, $result->score);
    }

    public function test_cert_at_31_days_is_ok(): void
    {
        $result = $this->makeCheck($this->fixtureCert(31))->run('example.com');
        $this->assertSame('ok', $result->status);
        $this->assertSame(100, $result->score);
    }
}
