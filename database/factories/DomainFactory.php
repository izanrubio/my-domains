<?php

namespace Database\Factories;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->domainName(),
            'cloudflare_zone_id' => $this->faker->uuid(),
            'status' => 'active',
            'expires_at' => null,
            'expiry_source' => null,
            'auto_renew' => false,
            'last_synced_at' => null,
            'notes' => null,
        ];
    }

    public function expiringSoon(int $days = 15): static
    {
        return $this->state(['expires_at' => now()->addDays($days), 'expiry_source' => 'whois']);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDays(5), 'expiry_source' => 'whois']);
    }

    public function autoRenew(): static
    {
        return $this->state(['auto_renew' => true]);
    }
}
