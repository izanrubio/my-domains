<?php

namespace Database\Factories;

use App\Models\Domain;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScanFactory extends Factory
{
    protected $model = Scan::class;

    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'status' => 'running',
            'health_score' => null,
            'results' => null,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    public function completed(int $score = 85): static
    {
        return $this->state([
            'status' => 'completed',
            'health_score' => $score,
            'results' => ['checks' => []],
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'completed_at' => now(),
        ]);
    }
}
