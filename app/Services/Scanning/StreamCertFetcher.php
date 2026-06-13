<?php

namespace App\Services\Scanning;

use App\Services\Scanning\Contracts\CertFetcherInterface;

class StreamCertFetcher implements CertFetcherInterface
{
    public function fetch(string $host): ?array
    {
        $ctx = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer'       => true,
                'verify_peer_name'  => true,
            ],
        ]);

        $socket = @stream_socket_client(
            "ssl://{$host}:443",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $ctx,
        );

        if (! $socket) {
            return null;
        }

        $params = stream_context_get_params($socket);
        fclose($socket);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if ($cert === null) {
            return null;
        }

        return openssl_x509_parse($cert) ?: null;
    }
}
