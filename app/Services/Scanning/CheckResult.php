<?php

namespace App\Services\Scanning;

final readonly class CheckResult
{
    public function __construct(
        public string $key,
        public string $label,
        public string $status,   // ok | warning | fail | skipped
        public int    $score,    // 0-100 for this check
        public int    $weight,   // contribution to overall score denominator
        public string $summary,  // one-line human description
        public array  $findings, // structured detail items for the UI
    ) {}

    public static function ok(
        string $key,
        string $label,
        int    $weight,
        string $summary,
        array  $findings = [],
    ): self {
        return new self($key, $label, 'ok', 100, $weight, $summary, $findings);
    }

    public static function warning(
        string $key,
        string $label,
        int    $weight,
        int    $score,
        string $summary,
        array  $findings = [],
    ): self {
        return new self($key, $label, 'warning', $score, $weight, $summary, $findings);
    }

    public static function fail(
        string $key,
        string $label,
        int    $weight,
        string $summary,
        array  $findings = [],
    ): self {
        return new self($key, $label, 'fail', 0, $weight, $summary, $findings);
    }

    public static function skipped(
        string $key,
        string $label,
        int    $weight,
        string $summary = 'Could not complete check',
    ): self {
        return new self($key, $label, 'skipped', 0, $weight, $summary, []);
    }

    public function toArray(): array
    {
        return [
            'key'      => $this->key,
            'label'    => $this->label,
            'status'   => $this->status,
            'score'    => $this->score,
            'weight'   => $this->weight,
            'summary'  => $this->summary,
            'findings' => $this->findings,
        ];
    }
}
