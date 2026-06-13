<?php

namespace Database\Factories;

use App\Models\DnsRecord;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

class DnsRecordFactory extends Factory
{
    protected $model = DnsRecord::class;

    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'cloudflare_record_id' => $this->faker->uuid(),
            'type' => 'A',
            'name' => $this->faker->domainName(),
            'content' => $this->faker->ipv4(),
            'ttl' => 1,
            'proxied' => false,
        ];
    }
}
