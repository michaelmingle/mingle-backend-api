<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Http\Resources\PublicUserResource;
use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\Event;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Everything behind "who is around me" -- the /nearby feed and the per-event
 * "Who's Mingle-ing?" list.
 *
 * Proximity is a lat/lng haversine calculation rather than real BLE ranging.
 * See the README for why that is a deliberate choice for this pass.
 */
class DiscoveryService
{
    public const SORTS = ['closest', 'relevant', 'shared_interests', 'profession'];

    /**
     * Discoverable users around a point, nearest first by default.
     *
     * @param  array<string, mixed>  $filters
     */
    public function nearby(
        User $viewer,
        float $latitude,
        float $longitude,
        array $filters = [],
        string $sort = 'closest',
        ?int $perPage = null,
    ): LengthAwarePaginator {
        $radiusMeters = (int) ($filters['radius_meters']
            ?? $viewer->profile?->discovery_radius_meters
            ?? config('mingle.discovery.default_radius_meters'));

        $radiusMeters = min($radiusMeters, (int) config('mingle.discovery.max_radius_meters'));

        $distance = Profile::distanceExpression($latitude, $longitude);

        $query = $this->baseDiscoverableQuery($viewer)
            ->whereNotNull('profiles.latitude')
            ->whereNotNull('profiles.longitude')
            ->selectRaw("$distance as distance_meters")
            ->whereRaw($distance.' <= '.Profile::numericLiteral($radiusMeters));

        $this->applyFilters($query, $filters);
        $this->applySort($query, $sort, $distance);

        return $query->paginate($perPage ?? (int) config('mingle.discovery.page_size'));
    }

    /**
     * Attendees of an event who opted into networking.
     *
     * `event_attendees.is_networking_enabled` is the per-event discoverability
     * switch, so it -- not the global `profiles.is_discoverable` flag -- gates
     * this list: a user can be invisible on the city-wide /nearby feed while
     * still being open to meeting people in the room. Everything else matches
     * /nearby: blocked users in either direction are excluded, suspended and
     * banned accounts are excluded, and contact details stay behind each
     * subject's own sharing preferences. Pass $requireDiscoverable to also
     * demand the global flag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function eventAttendees(
        User $viewer,
        Event $event,
        array $filters = [],
        ?int $perPage = null,
        bool $requireDiscoverable = false,
    ): LengthAwarePaginator {
        $query = User::query()
            ->select('users.*')
            ->join('event_attendees', 'event_attendees.user_id', '=', 'users.id')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->where('event_attendees.event_id', $event->id)
            ->where('event_attendees.is_networking_enabled', true)
            ->where('users.id', '!=', $viewer->id)
            ->active()
            ->notBlockedWith($viewer->id)
            ->with(['profile', 'contactPreferences', 'interests', 'skills']);

        if ($requireDiscoverable) {
            $query->where('profiles.is_discoverable', true);
        }

        $this->applyFilters($query, $filters);
        $this->withSharedInterestCount($query, $viewer);
        $query->orderByDesc('shared_interests_count')->orderBy('users.name');

        return $query->paginate($perPage ?? (int) config('mingle.discovery.page_size'));
    }

    /**
     * Renders users through PublicUserResource with the viewer context (shared
     * interests, connectedness, pending requests) resolved in bulk -- one query
     * per concern rather than one per row.
     *
     * Contact details are always omitted here: list surfaces are broadcast to
     * strangers, so phone/email/WhatsApp are only ever revealed on a deliberate
     * profile view.
     *
     * @param  EloquentCollection<int, User>|Collection<int, User>  $users
     * @return array<int, array<string, mixed>>
     */
    public function present(Collection|EloquentCollection $users, User $viewer, bool $includePendingState = false): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $ids = $users->pluck('id')->all();
        $connectedIds = $this->connectedIds($viewer, $ids);
        $pendingIds = $includePendingState ? $this->pendingRequestIds($viewer, $ids) : [];
        $viewerInterests = $viewer->interests()->pluck('interests.name', 'interests.id');

        return $users->map(function (User $user) use ($connectedIds, $pendingIds, $viewerInterests, $includePendingState) {
            $shared = $user->relationLoaded('interests')
                ? $user->interests->pluck('name', 'id')->intersectByKeys($viewerInterests)->values()->all()
                : [];

            $resource = (new PublicUserResource($user))
                ->withoutContact()
                ->connected(in_array($user->id, $connectedIds, true))
                ->sharedInterests($shared)
                ->distance($user->distance_meters !== null ? (float) $user->distance_meters : null);

            if ($includePendingState) {
                $resource->pendingRequest(in_array($user->id, $pendingIds, true));
            }

            return $resource->resolve(request());
        })->all();
    }

    /**
     * Records that these users were surfaced to the viewer during any open
     * networking session, feeding the "23 people discovered" recap.
     *
     * @param  array<int, int>  $userIds
     */
    public function creditDiscoveries(User $viewer, array $userIds, ?int $eventId = null): void
    {
        if ($userIds === []) {
            return;
        }

        $viewer->networkingSessions()
            ->open()
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))
            ->increment('discovered_count', count($userIds));
    }

    // ------------------------------------------------------------------ internals

    private function baseDiscoverableQuery(User $viewer): Builder
    {
        return User::query()
            ->select('users.*')
            ->join('profiles', 'profiles.user_id', '=', 'users.id')
            ->where('users.id', '!=', $viewer->id)
            ->where('users.status', UserStatus::Active->value)
            ->where('profiles.is_discoverable', true)
            ->notBlockedWith($viewer->id)
            ->with(['profile', 'contactPreferences', 'interests', 'skills']);
    }

    /** @param  array<string, mixed>  $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (filled($filters['industry'] ?? null)) {
            $query->where('profiles.industry', 'like', '%'.$filters['industry'].'%');
        }

        if (filled($filters['profession'] ?? null)) {
            $query->where('profiles.professional_title', 'like', '%'.$filters['profession'].'%');
        }

        if (filled($filters['looking_for'] ?? null)) {
            // looking_for is a JSON array of short slugs; a LIKE on the encoded
            // column keeps this portable across MySQL and SQLite.
            $query->where('profiles.looking_for', 'like', '%"'.$filters['looking_for'].'"%');
        }

        if (filled($filters['skill'] ?? null)) {
            $skill = $filters['skill'];
            $query->whereHas('skills', function (Builder $q) use ($skill) {
                is_numeric($skill)
                    ? $q->where('skills.id', (int) $skill)
                    : $q->where('skills.name', 'like', '%'.$skill.'%');
            });
        }

        // Free-text catch-all used by the /nearby `filter` parameter.
        if (filled($filters['filter'] ?? null)) {
            $like = '%'.$filters['filter'].'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('users.name', 'like', $like)
                    ->orWhere('profiles.professional_title', 'like', $like)
                    ->orWhere('profiles.industry', 'like', $like)
                    ->orWhere('profiles.looking_for', 'like', $like);
            });
        }
    }

    private function applySort(Builder $query, string $sort, string $distance): void
    {
        $viewer = request()->user();

        match ($sort) {
            'profession' => $query->orderByRaw('profiles.professional_title is null')
                ->orderBy('profiles.professional_title')
                ->orderByRaw("$distance asc"),
            'shared_interests', 'relevant' => (function () use ($query, $viewer, $distance, $sort) {
                $this->withSharedInterestCount($query, $viewer);
                $query->orderByDesc('shared_interests_count');

                if ($sort === 'relevant') {
                    // Relevance = shared interests, then verified accounts, then proximity.
                    $query->orderByDesc('users.is_verified');
                }

                $query->orderByRaw("$distance asc");
            })(),
            default => $query->orderByRaw("$distance asc"),
        };
    }

    /** Adds a `shared_interests_count` column relative to the viewer. */
    private function withSharedInterestCount(Builder $query, ?User $viewer): void
    {
        $interestIds = $viewer ? $viewer->interests()->pluck('interests.id')->all() : [];

        if ($interestIds === []) {
            $query->selectRaw('0 as shared_interests_count');

            return;
        }

        $placeholders = implode(',', array_fill(0, count($interestIds), '?'));

        $query->selectRaw(
            "(select count(*) from user_interests ui where ui.user_id = users.id and ui.interest_id in ($placeholders)) as shared_interests_count",
            $interestIds
        );
    }

    /**
     * @param  array<int, int>  $candidateIds
     * @return array<int, int>
     */
    private function connectedIds(User $viewer, array $candidateIds): array
    {
        $connected = Connection::query()
            ->forUser($viewer->id)
            ->get(['user_one_id', 'user_two_id'])
            ->map(fn (Connection $c) => $c->otherUserId($viewer->id))
            ->all();

        return array_values(array_intersect($connected, $candidateIds));
    }

    /**
     * @param  array<int, int>  $candidateIds
     * @return array<int, int>
     */
    private function pendingRequestIds(User $viewer, array $candidateIds): array
    {
        $pending = ConnectionRequest::query()
            ->pending()
            ->where(function (Builder $q) use ($viewer) {
                $q->where('sender_id', $viewer->id)->orWhere('receiver_id', $viewer->id);
            })
            ->get(['sender_id', 'receiver_id'])
            ->map(fn (ConnectionRequest $r) => $r->sender_id === $viewer->id ? $r->receiver_id : $r->sender_id)
            ->all();

        return array_values(array_intersect($pending, $candidateIds));
    }
}
