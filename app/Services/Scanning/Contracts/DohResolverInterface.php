<?php

namespace App\Services\Scanning\Contracts;

interface DohResolverInterface
{
    /**
     * Query Cloudflare DNS-over-HTTPS for $name/$type.
     * Returns the decoded JSON response array.
     * Throws \RuntimeException on HTTP failure.
     *
     * @return array{Status: int, AD: bool, Answer: list<array{name: string, type: int, TTL: int, data: string}>}
     */
    public function resolve(string $name, string $type): array;
}
