<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organizer_id',
        'title',
        'slug',
        'description',
        'banner_url',
        'location_text',
        'latitude',
        'longitude',
        'starts_at',
        'ends_at',
        'category',
        'status',
        'attendee_count',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => EventStatus::class,
            'attendee_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (blank($event->slug)) {
                $event->slug = self::uniqueSlug($event->title ?? 'event');
            }
        });
    }

    // ---------------------------------------------------------------- relations

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_attendees')
            ->withPivot(['is_networking_enabled', 'joined_at'])
            ->withTimestamps();
    }

    public function eventAttendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class);
    }

    public function networkingSessions(): HasMany
    {
        return $this->hasMany(NetworkingSession::class);
    }

    // ------------------------------------------------------------------- scopes

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EventStatus::Published->value);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('starts_at')->orWhere('starts_at', '>=', now());
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('location_text', 'like', $like)
                ->orWhere('category', 'like', $like);
        });
    }

    // ------------------------------------------------------------------ helpers

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function isAttendedBy(int|User $user): bool
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->eventAttendees()->where('user_id', $id)->exists();
    }

    public function refreshAttendeeCount(): void
    {
        $this->forceFill(['attendee_count' => $this->eventAttendees()->count()])->save();
    }
}
