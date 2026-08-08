<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Connection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'connection_request_id',
        'event_id',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- relations

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function connectionRequest(): BelongsTo
    {
        return $this->belongsTo(ConnectionRequest::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(UserNote::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(UserFavorite::class);
    }

    // ------------------------------------------------------------------- scopes

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('user_one_id', $userId)->orWhere('user_two_id', $userId);
        });
    }

    public function scopeBetweenUsers(Builder $query, int $a, int $b): Builder
    {
        [$one, $two] = self::canonicalPair($a, $b);

        return $query->where('user_one_id', $one)->where('user_two_id', $two);
    }

    public function scopeFavoritedBy(Builder $query, int $userId): Builder
    {
        return $query->whereHas('favorites', fn (Builder $q) => $q->where('user_id', $userId));
    }

    public function scopeFromEvents(Builder $query): Builder
    {
        return $query->whereNotNull('event_id');
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Connections are stored with the smaller user id first so a pair maps to
     * exactly one row.
     *
     * @return array{0: int, 1: int}
     */
    public static function canonicalPair(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    public function involves(int|User $user): bool
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->user_one_id === $id || $this->user_two_id === $id;
    }

    /** The other participant, from $user's point of view. */
    public function otherUser(int|User $user): ?User
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->user_one_id === $id ? $this->userTwo : $this->userOne;
    }

    public function otherUserId(int|User $user): int
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->user_one_id === $id ? $this->user_two_id : $this->user_one_id;
    }

    public function noteFor(int|User $user): ?UserNote
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->notes()->where('owner_id', $id)->first();
    }

    public function isFavoritedBy(int|User $user): bool
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->favorites()->where('user_id', $id)->exists();
    }
}
