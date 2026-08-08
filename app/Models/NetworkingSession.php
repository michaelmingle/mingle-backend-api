<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One "discoverability session" -- e.g. "Tech Summit 2026: 23 people discovered,
 * 8 connections made". Opened when a user turns discoverability on, closed when
 * they turn it off.
 */
class NetworkingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_id',
        'started_at',
        'ended_at',
        'discovered_count',
        'connections_count',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'discovered_count' => 'integer',
            'connections_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
