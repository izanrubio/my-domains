<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDnsRecordRequest;
use App\Http\Requests\UpdateDnsRecordRequest;
use App\Models\DnsRecord;
use App\Models\Domain;
use App\Services\CloudflareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DnsController extends Controller
{
    private function resolveCloudflareService(Request $request): CloudflareService
    {
        $token = $request->user()->getSetting('cloudflare_api_token');

        if (! $token) {
            abort(422, 'Cloudflare API token not configured. Add it in Settings.');
        }

        return new CloudflareService($token);
    }

    private function requireZoneId(Domain $domain): string
    {
        if (! $domain->cloudflare_zone_id) {
            abort(422, 'Domain has no Cloudflare zone ID. Run a sync first.');
        }

        return $domain->cloudflare_zone_id;
    }

    public function index(Request $request, Domain $domain): JsonResponse
    {
        $zoneId = $this->requireZoneId($domain);
        $cf = $this->resolveCloudflareService($request);

        $cfRecords = $cf->listDnsRecords($zoneId);
        $cfIds = [];

        foreach ($cfRecords as $rec) {
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

        // Remove local records that no longer exist in CF
        $domain->dnsRecords()->whereNotIn('cloudflare_record_id', $cfIds)->delete();

        return response()->json($domain->dnsRecords()->get());
    }

    public function store(StoreDnsRecordRequest $request, Domain $domain): JsonResponse
    {
        $zoneId = $this->requireZoneId($domain);
        $cf = $this->resolveCloudflareService($request);

        $cfRecord = $cf->createDnsRecord($zoneId, $request->validated());

        $local = DnsRecord::create([
            'domain_id' => $domain->id,
            'cloudflare_record_id' => $cfRecord['id'],
            'type' => $cfRecord['type'],
            'name' => $cfRecord['name'],
            'content' => $cfRecord['content'],
            'ttl' => $cfRecord['ttl'],
            'proxied' => $cfRecord['proxied'] ?? false,
        ]);

        return response()->json($local, 201);
    }

    public function update(UpdateDnsRecordRequest $request, Domain $domain, string $recordId): JsonResponse
    {
        $zoneId = $this->requireZoneId($domain);
        $cf = $this->resolveCloudflareService($request);

        $cfRecord = $cf->updateDnsRecord($zoneId, $recordId, $request->validated());

        $local = $domain->dnsRecords()->where('cloudflare_record_id', $recordId)->firstOrFail();
        $local->update([
            'type' => $cfRecord['type'],
            'name' => $cfRecord['name'],
            'content' => $cfRecord['content'],
            'ttl' => $cfRecord['ttl'],
            'proxied' => $cfRecord['proxied'] ?? false,
        ]);

        return response()->json($local);
    }

    public function destroy(Request $request, Domain $domain, string $recordId): JsonResponse
    {
        $zoneId = $this->requireZoneId($domain);
        $cf = $this->resolveCloudflareService($request);

        $cf->deleteDnsRecord($zoneId, $recordId);

        $domain->dnsRecords()->where('cloudflare_record_id', $recordId)->delete();

        return response()->json(null, 204);
    }
}
