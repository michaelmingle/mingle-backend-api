<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nearby\NearbyRequest;
use App\Http\Requests\Nearby\UpdateDiscoverabilityRequest;
use App\Models\NetworkingSession;
use App\Services\DiscoveryService;
use App\Services\ProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class NearbyController extends Controller
{
    public function __construct(
        private readonly DiscoveryService $discovery,
        private readonly ProfileService $profiles,
    ) {}

    /**
     * Toggling discoverability on opens a networking session and off closes it,
     * which is what powers the "Tech Summit 2026: 23 discovered, 8 connections"
     * recap.
     */
    public function updateDiscoverability(UpdateDiscoverabilityRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->profiles->bootstrapFor($user)->profile;

        $isDiscoverable = $request->boolean('is_discoverable');

        $profile->fill(array_filter([
            'discovery_radius_meters' => $request->input('discovery_radius_meters'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
        ], fn ($value) => $value !== null));

        $profile->is_discoverable = $isDiscoverable;
        $profile->save();

        $session = $isDiscoverable
            ? $this->openSession($request)
            : $this->closeSessions($request);

        return $this->ok([
            'is_discoverable' => $profile->is_discoverable,
            'discovery_radius_meters' => $profile->discovery_radius_meters,
            'latitude' => $profile->latitude,
            'longitude' => $profile->longitude,
            'networking_session' => $session,
        ], $isDiscoverable ? 'You are now discoverable.' : 'You are no longer discoverable.');
    }

    public function index(NearbyRequest $request): JsonResponse
    {
        $viewer = $request->user();

        $results = $this->discovery->nearby(
            $viewer,
            (float) $request->input('lat'),
            (float) $request->input('lng'),
            $request->filters(),
            $request->input('sort', 'closest'),
            $request->has('per_page') ? (int) $request->input('per_page') : null,
        );

        $this->discovery->creditDiscoveries($viewer, $results->getCollection()->pluck('id')->all());

        $items = $this->discovery->present($results->getCollection(), $viewer, includePendingState: true);

        return ApiResponse::success([
            'items' => $items,
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
                'has_more' => $results->hasMorePages(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function openSession(UpdateDiscoverabilityRequest $request): array
    {
        $user = $request->user();
        $eventId = $request->input('event_id');

        $session = $user->networkingSessions()->open()->where('event_id', $eventId)->first()
            ?? $user->networkingSessions()->create([
                'event_id' => $eventId,
                'started_at' => now(),
            ]);

        return $this->presentSession($session);
    }

    /** @return array<string, mixed>|null */
    private function closeSessions(UpdateDiscoverabilityRequest $request): ?array
    {
        $user = $request->user();

        $sessions = $user->networkingSessions()->open()->get();
        $sessions->each(fn (NetworkingSession $session) => $session->update(['ended_at' => now()]));

        return $sessions->isEmpty() ? null : $this->presentSession($sessions->last());
    }

    /** @return array<string, mixed> */
    private function presentSession(NetworkingSession $session): array
    {
        return [
            'id' => $session->id,
            'event_id' => $session->event_id,
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'discovered_count' => $session->discovered_count,
            'connections_count' => $session->connections_count,
        ];
    }
}
