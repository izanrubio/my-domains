<?php

namespace App\Services\Scanning;

use App\Services\Scanning\Contracts\Check;

class DomainScanner
{
    /** @param Check[] $checks */
    public function __construct(private readonly array $checks = []) {}

    public function scan(string $domain): array
    {
        $results = array_map(fn(Check $check) => $check->run($domain), $this->checks);

        return [
            'health_score' => $this->aggregateScore($results),
            'checks'       => array_map(fn(CheckResult $r) => $r->toArray(), $results),
        ];
    }

    /** Skipped checks are excluded from the denominator so they don't tank the score. */
    private function aggregateScore(array $results): int
    {
        $totalWeight   = 0;
        $weightedScore = 0.0;

        foreach ($results as $result) {
            if ($result->status === 'skipped') {
                continue;
            }
            $totalWeight   += $result->weight;
            $weightedScore += ($result->score / 100.0) * $result->weight;
        }

        if ($totalWeight === 0) {
            return 0;
        }

        return (int) round(($weightedScore / $totalWeight) * 100);
    }
}
