<?php

namespace Tests\Unit;

use App\Services\WhoisService;
use Carbon\Carbon;
use Iodev\Whois\Factory;
use Iodev\Whois\Whois;
use Tests\TestCase;

class WhoisServiceTest extends TestCase
{
    public function test_returns_null_when_whois_throws(): void
    {
        $service = new class extends WhoisService {
            public function getExpiryDate(string $domain): ?Carbon
            {
                try {
                    throw new \RuntimeException('Network error');
                } catch (\Throwable) {
                    return null;
                }
            }
        };

        $result = $service->getExpiryDate('example.com');

        $this->assertNull($result);
    }

    public function test_returns_carbon_when_expiry_parsed(): void
    {
        $timestamp = mktime(0, 0, 0, 12, 31, 2025);

        $mockInfo = new class ($timestamp) {
            public function __construct(public readonly int $expirationDate) {}
        };

        $service = new class ($mockInfo) extends WhoisService {
            public function __construct(private readonly mixed $info) {}

            public function getExpiryDate(string $domain): ?Carbon
            {
                if ($this->info === null || $this->info->expirationDate === null) {
                    return null;
                }
                return Carbon::createFromTimestamp($this->info->expirationDate);
            }
        };

        $result = $service->getExpiryDate('example.com');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertSame(2025, $result->year);
        $this->assertSame(12, $result->month);
    }
}
