<?php

namespace App\Services\Scanning\Checks;

use App\Services\Scanning\CheckResult;
use App\Services\Scanning\Contracts\Check;
use Illuminate\Support\Facades\Http;

class ReachabilityCheck implements Check
{
    public const KEY    = 'reachability';
    public const LABEL  = 'Reachability';
    public const WEIGHT = 5;

    public function run(string $domain): CheckResult
    {
        try {
            return $this->perform($domain);
        } catch (\Throwable) {
            return CheckResult::fail(
                self::KEY, self::LABEL, self::WEIGHT,
                "{$domain}: unreachable (connection failed)",
            );
        }
    }

    private function perform(string $domain): CheckResult
    {
        $start    = microtime(true);
        $response = Http::timeout(15)->get("https://{$domain}");
        $elapsed  = (int) round((microtime(true) - $start) * 1000);

        $statusCode = $response->status();
        $tech       = $this->detectTech($response);

        $finding = [
            'status_code'      => $statusCode,
            'response_time_ms' => $elapsed,
            'final_url'        => "https://{$domain}",
            'tech'             => $tech,
        ];

        $techStr = $tech ? " · {$tech}" : '';
        $summary = "{$domain}: HTTP {$statusCode}, {$elapsed}ms{$techStr}";

        if ($statusCode >= 500) {
            return CheckResult::fail(
                self::KEY, self::LABEL, self::WEIGHT,
                $summary,
                [$finding],
            );
        }

        if ($statusCode >= 400) {
            return CheckResult::warning(
                self::KEY, self::LABEL, self::WEIGHT, 30,
                $summary,
                [$finding],
            );
        }

        return CheckResult::ok(
            self::KEY, self::LABEL, self::WEIGHT,
            $summary,
            [$finding],
        );
    }

    private function detectTech(\Illuminate\Http\Client\Response $response): ?string
    {
        if ($response->hasHeader('CF-Ray') || $response->hasHeader('cf-cache-status')) {
            return 'Cloudflare';
        }

        $server = $response->header('Server');
        if ($server !== '' && $server !== null) {
            return $server;
        }

        $poweredBy = $response->header('X-Powered-By');
        if ($poweredBy !== '' && $poweredBy !== null) {
            return $poweredBy;
        }

        return null;
    }
}
