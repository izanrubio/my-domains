<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DnsRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'cloudflare_record_id',
        'type',
        'name',
        'content',
        'ttl',
        'proxied',
    ];

    protected $casts = [
        'proxied' => 'boolean',
        'ttl' => 'integer',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
