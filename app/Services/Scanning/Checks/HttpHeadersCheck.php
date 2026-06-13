<?php

namespace App\Services\Scanning\Checks;

use App\Services\Scanning\CheckResult;
use App\Services\Scanning\Contracts\Check;
use Illuminate\Support\Facades\Http;

class HttpHeadersCheck implements Check
{
    public const KEY    = 'http_headers';
    public const LABEL  = 'HTTP Security Headers';
    public const WEIGHT = 15;

    // Score budget (sums to 100)
    private const SCORES = [
        'https_redirect'          => 15,
        'Strict-Transport-Security' => 20,
        'Content-Security-Policy'   => 20,
        'X-Frame-Options'           => 15,
        'X-Content-Type-Options'    => 10,
        'Referrer-Policy'           => 10,
        'Permissions-Policy'        => 10,
    ];

    public function run(string $domain): CheckResult
    {
        try {
            return $this->perform($domain);
        } catch (\Throwable) {
            return CheckResult::skipped(
                self::KEY, self::LABEL, self::WEIGHT,
                'Could not reach domain to check HTTP headers',
            );
        }
    }

    private function perform(string $domain): CheckResult
    {
        // Check HTTP → HTTPS redirect (don't follow so we can inspect the 3xx)
        $redirectsToHttps = false;
        $redirectChain    = ["http://{$domain}"];

        try {
            $httpResp = Http::withoutRedirecting()
                ->timeout(10)
                ->get("http://{$domain}");

            if (
                in_array($httpResp->status(), [301, 302, 307, 308], true)
                && str_starts_with($httpResp->header('Location') ?? '', 'https://')
            ) {
                $redirectsToHttps = true;
                $redirectChain[]  = $httpResp->header('Location');
            }
        } catch (\Throwable) {
            // HTTP port unreachable; continue to check HTTPS headers
        }

        // Fetch HTTPS response for security-header inspection
        $httpsResp = Http::timeout(15)->get("https://{$domain}");

        $headerFindings = [];
        $score          = $redirectsToHttps ? self::SCORES['https_redirect'] : 0;

        foreach (array_keys(self::SCORES) as $key) {
            if ($key === 'https_redirect') {
                continue;
            }

            $present = $httpsResp->hasHeader($key);
            $value   = $present ? $httpsResp->header($key) : null;

            if ($present) {
                $score += self::SCORES[$key];
            }

            $headerFindings[] = [
                'header'  => $key,
                'present' => $present,
                'value'   => $value,
            ];
        }

        $findings = array_merge(
            [['check' => 'https_redirect', 'present' => $redirectsToHttps, 'redirect_chain' => $redirectChain]],
            $headerFindings,
        );

        $summary = $this->buildSummary($domain, $redirectsToHttps, $score);

        if ($score === 100) {
            return CheckResult::ok(self::KEY, self::LABEL, self::WEIGHT, $summary, $findings);
        }

        if ($score >= 40) {
            return CheckResult::warning(self::KEY, self::LABEL, self::WEIGHT, $score, $summary, $findings);
        }

        return CheckResult::fail(self::KEY, self::LABEL, self::WEIGHT, $summary, $findings);
    }

    private function buildSummary(string $domain, bool $redirect, int $score): string
    {
        $parts = [$redirect ? 'HTTPS redirect ok' : 'no HTTPS redirect'];
        $parts[] = "{$score}/100 header score";
        return "{$domain}: " . implode(', ', $parts);
    }
}
