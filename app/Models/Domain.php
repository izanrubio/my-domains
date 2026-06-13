<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cloudflare_zone_id',
        'status',
        'expires_at',
        'expiry_source',
        'auto_renew',
        'last_synced_at',
        'notes',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'last_synced_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    public function dnsRecords(): HasMany
    {
        return $this->hasMany(DnsRecord::class);
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->expires_at, false);
    }
}
