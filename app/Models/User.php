<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'auth_provider',
        'is_admin',
        'is_verified',
        'is_premium',
        'status',
        'last_active_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_verified' => 'boolean',
            'is_premium' => 'boolean',
            'status' => UserStatus::class,
        ];
    }

    // ---------------------------------------------------------------- relations

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function contactPreferences(): HasOne
    {
        return $this->hasOne(ContactSharingPreference::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'user_skills')->withTimestamps();
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class, 'user_interests')->withTimestamps();
    }

    public function sentConnectionRequests(): HasMany
    {
        return $this->hasMany(ConnectionRequest::class, 'sender_id');
    }

    public function receivedConnectionRequests(): HasMany
    {
        return $this->hasMany(ConnectionRequest::class, 'receiver_id');
    }

    public function organizedEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    public function attendingEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_attendees')
            ->withPivot(['is_networking_enabled', 'joined_at'])
            ->withTimestamps();
    }

    public function eventAttendances(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(UserNote::class, 'owner_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(UserFavorite::class);
    }

    public function networkingSessions(): HasMany
    {
        return $this->hasMany(NetworkingSession::class);
    }

    public function profileViewsReceived(): HasMany
    {
        return $this->hasMany(ProfileView::class, 'viewed_user_id');
    }

    public function profileViewsMade(): HasMany
    {
        return $this->hasMany(ProfileView::class, 'viewer_id');
    }

    public function reportsFiled(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    /** Users this user has blocked. */
    public function blocks(): HasMany
    {
        return $this->hasMany(BlockedUser::class);
    }

    /** Users who have blocked this user. */
    public function blockedBy(): HasMany
    {
        return $this->hasMany(BlockedUser::class, 'blocked_user_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(QrToken::class);
    }

    /** All accepted connections this user is part of (either side of the pair). */
    public function connections(): Builder
    {
        return Connection::query()->forUser($this->id);
    }

    // ------------------------------------------------------------------ scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active->value);
    }

    /**
     * Exclude users involved in a block relationship with $userId, in either
     * direction -- blocking is symmetric for visibility purposes.
     */
    public function scopeNotBlockedWith(Builder $query, int $userId): Builder
    {
        return $query->whereNotExists(function ($sub) use ($userId) {
            $sub->select(DB::raw(1))
                ->from('blocked_users')
                ->whereColumn('blocked_users.blocked_user_id', 'users.id')
                ->where('blocked_users.user_id', $userId);
        })->whereNotExists(function ($sub) use ($userId) {
            $sub->select(DB::raw(1))
                ->from('blocked_users')
                ->whereColumn('blocked_users.user_id', 'users.id')
                ->where('blocked_users.blocked_user_id', $userId);
        });
    }

    // ------------------------------------------------------------------ helpers

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function hasBlocked(int|User $user): bool
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->blocks()->where('blocked_user_id', $id)->exists();
    }

    public function isBlockedBy(int|User $user): bool
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->blockedBy()->where('user_id', $id)->exists();
    }

    public function hasBlockRelationshipWith(int|User $user): bool
    {
        return $this->hasBlocked($user) || $this->isBlockedBy($user);
    }

    public function isConnectedWith(int|User $user): bool
    {
        $id = $user instanceof User ? $user->id : $user;

        return Connection::query()->betweenUsers($this->id, $id)->exists();
    }

    public function connectionWith(int|User $user): ?Connection
    {
        $id = $user instanceof User ? $user->id : $user;

        return Connection::query()->betweenUsers($this->id, $id)->first();
    }

    public function touchLastActive(): void
    {
        $this->forceFill(['last_active_at' => now()])->saveQuietly();
    }
}
