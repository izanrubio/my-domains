<?php

namespace App\Services;

use App\Exceptions\Cloudflare\CloudflareException;
use App\Exceptions\Cloudflare\InvalidTokenException;
use App\Exceptions\Cloudflare\RateLimitException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class CloudflareService
{
    private const BASE_URL = 'https://api.cloudflare.com/client/v4';

    public function __construct(private readonly string $token) {}

    public function listZones(): array
    {
        $zones = [];
        $page = 1;

        do {
            $response = $this->get('/zones', ['page' => $page, 'per_page' => 50]);
            $result = $response['result'];
            $zones = array_merge($zones, $result);
            $totalPages = $response['result_info']['total_pages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $zones;
    }

    public function getZone(string $zoneId): array
    {
        return $this->get("/zones/{$zoneId}")['result'];
    }

    public function listDnsRecords(string $zoneId): array
    {
        $records = [];
        $page = 1;

        do {
            $response = $this->get("/zones/{$zoneId}/dns_records", ['page' => $page, 'per_page' => 100]);
            $records = array_merge($records, $response['result']);
            $totalPages = $response['result_info']['total_pages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $records;
    }

    public function createDnsRecord(string $zoneId, array $data): array
    {
        return $this->post("/zones/{$zoneId}/dns_records", $data)['result'];
    }

    public function updateDnsRecord(string $zoneId, string $recordId, array $data): array
    {
        return $this->put("/zones/{$zoneId}/dns_records/{$recordId}", $data)['result'];
    }

    public function deleteDnsRecord(string $zoneId, string $recordId): void
    {
        $this->delete("/zones/{$zoneId}/dns_records/{$recordId}");
    }

    private function get(string $path, array $query = []): array
    {
        $response = Http::withToken($this->token)
            ->get(self::BASE_URL . $path, $query);

        return $this->handleResponse($response);
    }

    private function post(string $path, array $data): array
    {
        $response = Http::withToken($this->token)
            ->post(self::BASE_URL . $path, $data);

        return $this->handleResponse($response);
    }

    private function put(string $path, array $data): array
    {
        $response = Http::withToken($this->token)
            ->put(self::BASE_URL . $path, $data);

        return $this->handleResponse($response);
    }

    private function delete(string $path): array
    {
        $response = Http::withToken($this->token)
            ->delete(self::BASE_URL . $path);

        return $this->handleResponse($response);
    }

    private function handleResponse(Response $response): array
    {
        if ($response->status() === 401 || $response->status() === 403) {
            throw new InvalidTokenException('Invalid or insufficient Cloudflare API token.');
        }

        if ($response->status() === 429) {
            throw new RateLimitException('Cloudflare API rate limit exceeded.');
        }

        $body = $response->json();

        if (! ($body['success'] ?? false)) {
            $errors = collect($body['errors'] ?? [])->pluck('message')->implode(', ');
            throw new CloudflareException("Cloudflare API error: {$errors}");
        }

        return $body;
    }
}
