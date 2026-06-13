<?php

namespace App\Services\Scanning\Contracts;

interface CertFetcherInterface
{
    /**
     * Open a TLS connection to $host:443 and return openssl_x509_parse() result.
     * Returns null if HTTPS is unavailable or the cert cannot be retrieved.
     * Must never throw — return null for any connection failure so SslCheck
     * can return a clean 'fail' instead of 'skipped'.
     */
    public function fetch(string $host): ?array;
}
