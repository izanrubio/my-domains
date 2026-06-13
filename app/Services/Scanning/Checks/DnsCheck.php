<?php

namespace App\Services\Scanning\Checks;

use App\Services\Scanning\CheckResult;
use App\Services\Scanning\Contracts\Check;
use App\Services\Scanning\Contracts\DohResolverInterface;

class DnsCheck implements Check
{
    public const KEY    = 'dns';
    public const LABEL  = 'DNS Records';
    public const WEIGHT = 15;

    // Score budget: A/AAAA 40 + NS 20 + MX 10 + DNSSEC 30 = 100
    private const SCORE_ADDRESS = 40;
    private const SCORE_NS      = 20;
    private const SCORE_MX      = 10;
    private const SCORE_DNSSEC  = 30;

    public function __construct(private readonly DohResolverInterface $resolver) {}

    public function run(string $domain): CheckResult
    {
        try {
            return $this->perform($domain);
        } catch (\Throwable) {
            return CheckResult::skipped(
                self::KEY, self::LABEL, self::WEIGHT,
                'Could not reach DNS resolver',
            );
        }
    }

    private function perform(string $domain): CheckResult
    {
        $responses = [];
        foreach (['A', 'AAAA', 'NS', 'MX', 'TXT', 'SOA', 'CNAME', 'DNSKEY', 'DS'] as $type) {
            $responses[$type] = $this->resolver->resolve($domain, $type);
        }

        $aStatus = $responses['A']['Status'] ?? -1;
        $adFlag  = (bool) ($responses['A']['AD'] ?? false);

        if ($aStatus !== 0) {
            $reason = $aStatus === 3 ? 'NXDOMAIN' : "DNS error (status {$aStatus})";
            return CheckResult::fail(
                self::KEY, self::LABEL, self::WEIGHT,
                "{$domain}: {$reason}",
                [],
            );
        }

        $aRecords     = $this->data($responses['A']);
        $aaaaRecords  = $this->data($responses['AAAA']);
        $nsRecords    = $this->data($responses['NS']);
        $mxRecords    = $this->data($responses['MX']);
        $txtRecords   = $this->data($responses['TXT']);
        $soaRecords   = $this->data($responses['SOA']);
        $cnameRecords = $this->data($responses['CNAME']);
        $dnskeyRecords = $this->data($responses['DNSKEY']);
        $dsRecords    = $this->data($responses['DS']);

        $dnskeyFound   = $dnskeyRecords !== [];
        $dsFound       = $dsRecords !== [];
        // DNSSEC: either the resolver authenticated it (AD flag) or DNSKEY records exist
        $dnssecEnabled = $adFlag || $dnskeyFound;

        $findings = [
            ['type' => 'A',     'records' => $aRecords],
            ['type' => 'AAAA',  'records' => $aaaaRecords],
            ['type' => 'NS',    'records' => $nsRecords],
            ['type' => 'MX',    'records' => $mxRecords],
            ['type' => 'TXT',   'records' => $txtRecords],
            ['type' => 'SOA',   'records' => $soaRecords],
            [
                'type'         => 'DNSSEC',
                'enabled'      => $dnssecEnabled,
                'ad_flag'      => $adFlag,
                'dnskey_found' => $dnskeyFound,
                'ds_found'     => $dsFound,
            ],
        ];

        if ($cnameRecords !== []) {
            $findings[] = ['type' => 'CNAME', 'records' => $cnameRecords];
        }

        $hasAddress = $aRecords !== [] || $aaaaRecords !== [];

        $score = 0;
        if ($hasAddress)        $score += self::SCORE_ADDRESS;
        if ($nsRecords !== [])  $score += self::SCORE_NS;
        if ($mxRecords !== [])  $score += self::SCORE_MX;
        if ($dnssecEnabled)     $score += self::SCORE_DNSSEC;

        if ($score === 0) {
            return CheckResult::fail(
                self::KEY, self::LABEL, self::WEIGHT,
                "{$domain}: no address, NS, MX, or DNSSEC found",
                $findings,
            );
        }

        $nsCount = count($nsRecords);
        $summary = sprintf(
            '%s resolves%s · %s',
            $domain,
            $nsCount ? " · {$nsCount} NS" : '',
            $dnssecEnabled ? 'DNSSEC enabled' : 'no DNSSEC',
        );

        if ($score === 100) {
            return CheckResult::ok(self::KEY, self::LABEL, self::WEIGHT, $summary, $findings);
        }

        return CheckResult::warning(self::KEY, self::LABEL, self::WEIGHT, $score, $summary, $findings);
    }

    private function data(array $response): array
    {
        return array_column($response['Answer'] ?? [], 'data');
    }
}
