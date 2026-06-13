<?php

namespace App\Services\Scanning\Checks;

use App\Services\Scanning\CheckResult;
use App\Services\Scanning\Contracts\Check;
use App\Services\Scanning\Contracts\DohResolverInterface;

class BlacklistCheck implements Check
{
    public const KEY    = 'blacklist';
    public const LABEL  = 'Blacklist';
    public const WEIGHT = 15;

    private const IP_LISTS = [
        'zen.spamhaus.org',
        'bl.spamcop.net',
        'b.barracudacentral.org',
    ];

    private const DOMAIN_LISTS = [
        'dbl.spamhaus.org',
    ];

    public function __construct(private readonly DohResolverInterface $resolver) {}

    public function run(string $domain): CheckResult
    {
        try {
            return $this->perform($domain);
        } catch (\Throwable) {
            return CheckResult::skipped(
                self::KEY, self::LABEL, self::WEIGHT,
                'Could not perform blacklist check',
            );
        }
    }

    private function perform(string $domain): CheckResult
    {
        $findings   = [];
        $hasListed  = false;
        $allSkipped = true;

        // Resolve domain's IP for DNSBL queries
        $ip          = $this->resolveIp($domain);
        $reversedIp  = $ip !== null
            ? implode('.', array_reverse(explode('.', $ip)))
            : null;

        // IP-based DNSBL lists
        if ($reversedIp !== null) {
            foreach (self::IP_LISTS as $list) {
                $query  = "{$reversedIp}.{$list}";
                $status = $this->queryList($query);

                $findings[] = ['list' => $list, 'type' => 'ip', 'query' => $query, 'status' => $status];

                if ($status === 'listed')  { $hasListed  = true; }
                if ($status !== 'skipped') { $allSkipped = false; }
            }
        }

        // Domain-based blacklist
        foreach (self::DOMAIN_LISTS as $list) {
            $query  = "{$domain}.{$list}";
            $status = $this->queryList($query);

            $findings[] = ['list' => $list, 'type' => 'domain', 'query' => $query, 'status' => $status];

            if ($status === 'listed')  { $hasListed  = true; }
            if ($status !== 'skipped') { $allSkipped = false; }
        }

        if ($hasListed) {
            $listedOn = implode(', ', array_column(
                array_filter($findings, fn($f) => $f['status'] === 'listed'),
                'list',
            ));
            return CheckResult::fail(
                self::KEY, self::LABEL, self::WEIGHT,
                "{$domain}: listed on {$listedOn}",
                $findings,
            );
        }

        if ($allSkipped) {
            return CheckResult::skipped(
                self::KEY, self::LABEL, self::WEIGHT,
                'All blacklist lookups were unavailable (lists may block public resolvers)',
            );
        }

        $checked = count(array_filter($findings, fn($f) => $f['status'] !== 'skipped'));
        return CheckResult::ok(
            self::KEY, self::LABEL, self::WEIGHT,
            "{$domain}: not listed on {$checked} checked list(s)",
            $findings,
        );
    }

    private function resolveIp(string $domain): ?string
    {
        try {
            $response = $this->resolver->resolve($domain, 'A');
            $answers  = $response['Answer'] ?? [];
            return $answers !== [] ? $answers[0]['data'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Returns 'listed' | 'clean' | 'skipped'.
     * Only Status=0 with an Answer is a real listing.
     * SERVFAIL (2), other errors, or DoH failures → skipped (not fail).
     */
    private function queryList(string $query): string
    {
        try {
            $response = $this->resolver->resolve($query, 'A');
            $status   = $response['Status'] ?? -1;

            if ($status === 0 && ! empty($response['Answer'])) {
                return 'listed';
            }

            if ($status === 3) {
                return 'clean'; // NXDOMAIN = not listed
            }

            // SERVFAIL (2) or any other status = can't determine (Spamhaus blocks public resolvers)
            return 'skipped';
        } catch (\Throwable) {
            return 'skipped'; // DoH HTTP error
        }
    }
}
