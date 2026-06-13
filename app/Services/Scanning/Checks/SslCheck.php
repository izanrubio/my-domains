<?php

namespace App\Services\Scanning\Checks;

use App\Services\Scanning\CheckResult;
use App\Services\Scanning\Contracts\CertFetcherInterface;
use App\Services\Scanning\Contracts\Check;
use Carbon\Carbon;

class SslCheck implements Check
{
    public const KEY    = 'ssl';
    public const LABEL  = 'SSL/TLS Certificate';
    public const WEIGHT = 20;

    public function __construct(private readonly CertFetcherInterface $certFetcher) {}

    public function run(string $domain): CheckResult
    {
        try {
            return $this->perform($domain);
        } catch (\Throwable) {
            return CheckResult::skipped(
                self::KEY, self::LABEL, self::WEIGHT,
                'Could not inspect TLS certificate',
            );
        }
    }

    private function perform(string $domain): CheckResult
    {
        $parsed = $this->certFetcher->fetch($domain);

        if ($parsed === null) {
            return CheckResult::fail(
                self::KEY, self::LABEL, self::WEIGHT,
                "{$domain}: no HTTPS / could not retrieve certificate",
            );
        }

        $validTo   = Carbon::createFromTimestamp($parsed['validTo_time_t']);
        $validFrom = Carbon::createFromTimestamp($parsed['validFrom_time_t']);

        // Certificate expiry in days — distinct from the domain registration expiry
        $daysUntilCertExpiry = (int) now()->startOfDay()->diffInDays($validTo, false);

        $issuer  = $parsed['issuer']['O'] ?? $parsed['issuer']['CN'] ?? 'Unknown';
        $subject = $parsed['subject']['CN'] ?? $domain;
        $sans    = $this->parseSans($parsed['extensions']['subjectAltName'] ?? '');

        $finding = [
            'issuer'   => $issuer,
            'subject'  => $subject,
            'sans'     => $sans,
            'valid_from'             => $validFrom->toDateString(),
            'valid_to'               => $validTo->toDateString(),
            // NOTE: TLS certificate expiry — NOT domain registration expiry
            'days_until_cert_expiry' => $daysUntilCertExpiry,
            'expired'                => $daysUntilCertExpiry < 0,
        ];

        if ($daysUntilCertExpiry < 0) {
            return CheckResult::fail(
                self::KEY, self::LABEL, self::WEIGHT,
                "Certificate expired {$validTo->toDateString()} (issuer: {$issuer})",
                [$finding],
            );
        }

        if ($daysUntilCertExpiry <= 14) {
            return CheckResult::warning(
                self::KEY, self::LABEL, self::WEIGHT, 20,
                "Certificate expires in {$daysUntilCertExpiry} days — renew urgently (issuer: {$issuer})",
                [$finding],
            );
        }

        if ($daysUntilCertExpiry <= 30) {
            return CheckResult::warning(
                self::KEY, self::LABEL, self::WEIGHT, 50,
                "Certificate expires in {$daysUntilCertExpiry} days (issuer: {$issuer})",
                [$finding],
            );
        }

        return CheckResult::ok(
            self::KEY, self::LABEL, self::WEIGHT,
            "Certificate valid for {$daysUntilCertExpiry} days (issuer: {$issuer})",
            [$finding],
        );
    }

    private function parseSans(string $san): array
    {
        if ($san === '') {
            return [];
        }

        $result = [];
        foreach (explode(',', $san) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 'DNS:')) {
                $result[] = substr($part, 4);
            }
        }

        return $result;
    }
}
