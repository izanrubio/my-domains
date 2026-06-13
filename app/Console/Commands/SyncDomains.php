<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\DnsRecord;
use App\Models\User;
use App\Services\CloudflareService;
use App\Services\WhoisService;
use Illuminate\Console\Command;

class SyncDomains extends Command
{
    protected $signature = 'domains:sync {--user= : User ID whose CF token to use}';
    protected $description = 'Sync domains and DNS records from Cloudflare';

    public function handle(WhoisService $whoisService): int
    {
        try {
            $cf = $this->resolveCloudflareService();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Fetching zones from Cloudflare...');
        $zones = $cf->listZones();
        $this->info(count($zones) . ' zone(s) found.');

        foreach ($zones as $zone) {
            $domain = Domain::updateOrCreate(
                ['cloudflare_zone_id' => $zone['id']],
                [
                    'name' => $zone['name'],
                    'status' => $zone['status'],
                    'last_synced_at' => now(),
                ]
            );

            $this->syncDnsRecords($cf, $domain, $zone['id']);
            $this->maybeUpdateExpiry($domain, $whoisService);
        }

        $this->info('Sync complete.');
        return self::SUCCESS;
    }

    private function syncDnsRecords(CloudflareService $cf, Domain $domain, string $zoneId): void
    {
        $records = $cf->listDnsRecords($zoneId);
        $cfIds = [];

        foreach ($records as $rec) {
            DnsRecord::updateOrCreate(
                ['domain_id' => $domain->id, 'cloudflare_record_id' => $rec['id']],
                [
                    'type' => $rec['type'],
                    'name' => $rec['name'],
                    'content' => $rec['content'],
                    'ttl' => $rec['ttl'],
                    'proxied' => $rec['proxied'] ?? false,
                ]
            );
            $cfIds[] = $rec['id'];
        }

        $domain->dnsRecords()->whereNotIn('cloudflare_record_id', $cfIds)->delete();
    }

    private function maybeUpdateExpiry(Domain $domain, WhoisService $whoisService): void
    {
        $needsWhois = $domain->expires_at === null
            || ($domain->expiry_source !== 'manual' && $domain->expires_at->isPast());

        if (! $needsWhois) {
            return;
        }

        $expiry = $whoisService->getExpiryDate($domain->name);

        if ($expiry !== null) {
            $domain->update(['expires_at' => $expiry, 'expiry_source' => 'whois']);
            $this->line("  Expiry updated for {$domain->name}: {$expiry->toDateString()}");
        }
    }

    private function resolveCloudflareService(): CloudflareService
    {
        $userId = $this->option('user');

        if ($userId) {
            $token = User::find($userId)?->getSetting('cloudflare_api_token');
        } else {
            $token = User::all()
                ->first(fn (User $u) => $u->getSetting('cloudflare_api_token') !== null)
                ?->getSetting('cloudflare_api_token')
                ?? config('services.cloudflare.token');
        }

        if (! ($token ?? null)) {
            throw new \RuntimeException(
                'No Cloudflare API token found. Configure it in Settings or set CLOUDFLARE_API_TOKEN.'
            );
        }

        return new CloudflareService($token);
    }
}
