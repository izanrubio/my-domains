<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scan extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'status',
        'health_score',
        'results',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'results' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'health_score' => 'integer',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
