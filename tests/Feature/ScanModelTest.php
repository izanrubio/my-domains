<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_belongs_to_domain(): void
    {
        $domain = Domain::factory()->create();
        $scan = Scan::factory()->create(['domain_id' => $domain->id]);

        $this->assertInstanceOf(Domain::class, $scan->domain);
        $this->assertSame($domain->id, $scan->domain->id);
    }

    public function test_domain_has_many_scans(): void
    {
        $domain = Domain::factory()->create();
        Scan::factory()->count(3)->create(['domain_id' => $domain->id]);

        $this->assertCount(3, $domain->scans);
    }

    public function test_domain_latest_scan_returns_most_recent(): void
    {
        $domain = Domain::factory()->create();
        $old = Scan::factory()->completed(60)->create(['domain_id' => $domain->id, 'created_at' => now()->subMinutes(10)]);
        $new = Scan::factory()->completed(90)->create(['domain_id' => $domain->id, 'created_at' => now()]);

        $latest = $domain->latestScan;
        $this->assertSame($new->id, $latest->id);
    }

    public function test_scan_results_cast_to_array(): void
    {
        $scan = Scan::factory()->completed()->create();

        $this->assertIsArray($scan->results);
    }

    public function test_scan_factory_states(): void
    {
        $running = Scan::factory()->create();
        $this->assertSame('running', $running->status);
        $this->assertNull($running->health_score);

        $completed = Scan::factory()->completed(75)->create();
        $this->assertSame('completed', $completed->status);
        $this->assertSame(75, $completed->health_score);

        $failed = Scan::factory()->failed()->create();
        $this->assertSame('failed', $failed->status);
    }
}
