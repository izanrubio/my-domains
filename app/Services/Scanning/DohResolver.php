<?php

namespace App\Services\Scanning;

use App\Services\Scanning\Contracts\DohResolverInterface;
use Illuminate\Support\Facades\Http;

class DohResolver implements DohResolverInterface
{
    private const DOH_URL = 'https://cloudflare-dns.com/dns-query';

    public function resolve(string $name, string $type): array
    {
        $response = Http::withHeaders(['Accept' => 'application/dns-json'])
            ->get(self::DOH_URL, ['name' => $name, 'type' => $type]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "DoH request failed for {$name} {$type}: HTTP {$response->status()}"
            );
        }

        return $response->json();
    }
}
