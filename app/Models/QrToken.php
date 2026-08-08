<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Opaque token embedded in a user's personal QR code. Scans resolve through this
 * table so a printed/shared code never exposes an enumerable user id, and a user
 * can rotate their code by deactivating the current token.
 */
class QrToken extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'token', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (static::where('token', $token)->exists());

        return $token;
    }

    public function deepLink(): string
    {
        return rtrim((string) config('mingle.qr.deep_link_base'), '/').'/'.$this->token;
    }
}
