<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['user_id', 'key', 'value'];

    protected static array $encryptedKeys = ['cloudflare_api_token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getValueAttribute(?string $value): ?string
    {
        if ($value !== null && in_array($this->key, self::$encryptedKeys, true)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception) {
                return null;
            }
        }

        return $value;
    }

    public function setValueAttribute(?string $value): void
    {
        if ($value !== null && in_array($this->key, self::$encryptedKeys, true)) {
            $this->attributes['value'] = Crypt::encryptString($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }
}
