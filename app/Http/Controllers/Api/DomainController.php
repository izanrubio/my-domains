<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDomainRequest;
use App\Models\Domain;
use App\Services\WhoisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DomainController extends Controller
{
    public function index(): JsonResponse
    {
        $domains = Domain::orderByRaw('expires_at IS NULL, expires_at ASC')
            ->get()
            ->map(fn (Domain $d) => $d->toArray() + ['days_until_expiry' => $d->days_until_expiry]);

        return response()->json($domains);
    }

    public function show(Domain $domain): JsonResponse
    {
        $domain->load('dnsRecords');

        return response()->json(
            $domain->toArray() + [
                'days_until_expiry' => $domain->days_until_expiry,
                'dns_records' => $domain->dnsRecords,
            ]
        );
    }

    public function update(UpdateDomainRequest $request, Domain $domain): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('expires_at', $data) && $data['expires_at'] !== null) {
            $data['expiry_source'] = $data['expiry_source'] ?? 'manual';
        }

        $domain->update($data);

        return response()->json($domain->toArray() + ['days_until_expiry' => $domain->days_until_expiry]);
    }

    public function sync(Request $request): JsonResponse
    {
        Artisan::call('domains:sync', ['--user' => $request->user()->id]);

        return response()->json(['message' => 'Sync completed.']);
    }

    public function whois(Request $request, Domain $domain, WhoisService $whoisService): JsonResponse
    {
        $expiry = $whoisService->getExpiryDate($domain->name);

        if ($expiry === null) {
            return response()->json(['message' => 'Could not detect expiry date via WHOIS.'], 422);
        }

        $domain->update(['expires_at' => $expiry, 'expiry_source' => 'whois']);

        return response()->json($domain->toArray() + ['days_until_expiry' => $domain->days_until_expiry]);
    }
}
