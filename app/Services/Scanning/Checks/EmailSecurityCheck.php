<?php

namespace App\Services\Scanning\Checks;

use App\Services\Scanning\CheckResult;
use App\Services\Scanning\Contracts\Check;
use App\Services\Scanning\Contracts\DohResolverInterface;

class EmailSecurityCheck implements Check
{
    public const KEY    = 'email_security';
    public const LABEL  = 'Email Security';
    public const WEIGHT = 20;

    private const DKIM_SELECTORS = [
        'default', 'google', 'selector1', 'selector2', 'k1', 'mail', 'dkim',
    ];

    public function __construct(private readonly DohResolverInterface $resolver) {}

    public function run(string $domain): CheckResult
    {
        try {
            return $this->perform($domain);
        } catch (\Throwable) {
            return CheckResult::skipped(
                self::KEY, self::LABEL, self::WEIGHT,
                'Could not complete email security check',
            );
        }
    }

    private function perform(string $domain): CheckResult
    {
        // SPF — TXT at root starting with v=spf1
        $spfRecord = $this->findTxtRecord($domain, 'v=spf1');

        // DMARC — TXT at _dmarc.<domain> starting with v=DMARC1
        $dmarcRecord  = $this->findTxtRecord("_dmarc.{$domain}", 'v=DMARC1');
        $dmarcPolicy  = $dmarcRecord !== null ? $this->extractDmarcPolicy($dmarcRecord) : null;

        // DKIM — probe common selectors, report which ones responded
        [$dkimFound, $dkimChecked] = $this->probeDkim($domain);

        // MX
        $mxRecords = $this->resolveMx($domain);

        $findings = [
            ['type' => 'SPF',   'present' => $spfRecord !== null, 'record' => $spfRecord],
            ['type' => 'DMARC', 'present' => $dmarcRecord !== null, 'policy' => $dmarcPolicy, 'record' => $dmarcRecord],
            ['type' => 'DKIM',  'selectors_found' => $dkimFound, 'selectors_checked' => $dkimChecked],
            ['type' => 'MX',    'records' => $mxRecords],
        ];

        $score = 0;
        if ($spfRecord !== null) $score += 25;
        $score += match ($dmarcPolicy) {
            'reject'     => 35,
            'quarantine' => 25,
            'none'       => 15,
            default      => 0,
        };
        if ($dkimFound !== []) $score += 25;
        if ($mxRecords !== []) $score += 15;

        $summary = $this->buildSummary($domain, $spfRecord, $dmarcPolicy, $dkimFound, $mxRecords);

        if ($score >= 70) {
            return CheckResult::ok(self::KEY, self::LABEL, self::WEIGHT, $summary, $findings);
        }

        if ($score >= 30) {
            return CheckResult::warning(self::KEY, self::LABEL, self::WEIGHT, $score, $summary, $findings);
        }

        return CheckResult::fail(self::KEY, self::LABEL, self::WEIGHT, $summary, $findings);
    }

    private function findTxtRecord(string $name, string $prefix): ?string
    {
        $response = $this->resolver->resolve($name, 'TXT');

        foreach ($response['Answer'] ?? [] as $answer) {
            $data = $answer['data'] ?? '';
            if (str_starts_with(ltrim($data, '"'), $prefix)) {
                return trim($data, '"');
            }
        }

        return null;
    }

    /** @return array{list<string>, list<string>} [found, checked] */
    private function probeDkim(string $domain): array
    {
        $found   = [];
        $checked = self::DKIM_SELECTORS;

        foreach ($checked as $selector) {
            $response = $this->resolver->resolve("{$selector}._domainkey.{$domain}", 'TXT');
            if (!empty($response['Answer'])) {
                $found[] = $selector;
            }
        }

        return [$found, $checked];
    }

    private function resolveMx(string $domain): array
    {
        $response = $this->resolver->resolve($domain, 'MX');
        return array_column($response['Answer'] ?? [], 'data');
    }

    private function extractDmarcPolicy(string $record): ?string
    {
        if (preg_match('/\bp=(\w+)/i', $record, $m)) {
            return strtolower($m[1]);
        }
        return null;
    }

    private function buildSummary(
        string  $domain,
        ?string $spf,
        ?string $dmarcPolicy,
        array   $dkimFound,
        array   $mx,
    ): string {
        $parts = [];
        $parts[] = $spf !== null ? 'SPF ok' : 'no SPF';
        $parts[] = $dmarcPolicy !== null ? "DMARC {$dmarcPolicy}" : 'no DMARC';
        $parts[] = $dkimFound !== [] ? 'DKIM found' : 'no DKIM';
        $parts[] = $mx !== [] ? count($mx) . ' MX' : 'no MX';

        return $domain . ': ' . implode(', ', $parts);
    }
}
