<?php

namespace App\Models;

use App\Enums\ConnectionRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'status',
        'event_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConnectionRequestStatus::class,
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ConnectionRequestStatus::Pending->value);
    }

    public function scopeIncomingFor(Builder $query, int $userId): Builder
    {
        return $query->where('receiver_id', $userId);
    }

    public function scopeOutgoingFor(Builder $query, int $userId): Builder
    {
        return $query->where('sender_id', $userId);
    }

    /** Pending requests in either direction between two users. */
    public function scopePendingBetween(Builder $query, int $a, int $b): Builder
    {
        return $query->pending()->where(function (Builder $q) use ($a, $b) {
            $q->where(fn (Builder $x) => $x->where('sender_id', $a)->where('receiver_id', $b))
                ->orWhere(fn (Builder $x) => $x->where('sender_id', $b)->where('receiver_id', $a));
        });
    }

    public function isPending(): bool
    {
        return $this->status === ConnectionRequestStatus::Pending;
    }
}
