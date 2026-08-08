<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\EventNetworkingRequest;
use App\Http\Requests\Events\IndexEventsRequest;
use App\Http\Requests\Events\JoinEventRequest;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\DiscoveryService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(private readonly DiscoveryService $discovery) {}

    public function index(IndexEventsRequest $request): JsonResponse
    {
        $user = $request->user();

        $query = Event::query()
            ->with('organizer')
            ->search($request->input('search'))
            ->when($request->filled('category'), fn (Builder $q) => $q->where('category', $request->input('category')))
            ->when($request->boolean('upcoming'), fn (Builder $q) => $q->upcoming())
            ->when($request->boolean('mine'), fn (Builder $q) => $q->where('organizer_id', $user->id))
            ->when($request->boolean('attending'), fn (Builder $q) => $q->whereHas(
                'eventAttendees',
                fn (Builder $a) => $a->where('user_id', $user->id)
            ));

        // Drafts and pending events stay private to their organizer.
        $query->where(function (Builder $q) use ($user) {
            $q->where('status', EventStatus::Published->value)
                ->orWhere('organizer_id', $user->id);
        });

        $query->orderByRaw('starts_at is null')->orderBy('starts_at');

        return $this->paginated(EventResource::collection($query->paginate($this->perPage())));
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = Event::create(array_merge($request->validated(), [
            'organizer_id' => $request->user()->id,
            'status' => $request->input('status', EventStatus::Published->value),
        ]));

        return $this->created(new EventResource($event->load('organizer')), 'Event created.');
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $event->load(['organizer', 'eventAttendees']);

        return $this->ok(new EventResource($event));
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $event->update($request->validated());

        return $this->ok(new EventResource($event->fresh()->load('organizer')), 'Event updated.');
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return $this->ok(null, 'Event deleted.');
    }

    public function join(JoinEventRequest $request, Event $event): JsonResponse
    {
        $this->authorize('join', $event);

        $attendance = $event->eventAttendees()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'is_networking_enabled' => $request->has('is_networking_enabled')
                    ? $request->boolean('is_networking_enabled')
                    : true,
                'joined_at' => now(),
            ],
        );

        $event->refreshAttendeeCount();

        return $this->ok([
            'event_id' => $event->id,
            'is_attending' => true,
            'is_networking_enabled' => $attendance->is_networking_enabled,
            'attendee_count' => $event->attendee_count,
        ], 'Joined event.');
    }

    public function leave(Request $request, Event $event): JsonResponse
    {
        $event->eventAttendees()->where('user_id', $request->user()->id)->delete();
        $event->refreshAttendeeCount();

        return $this->ok([
            'event_id' => $event->id,
            'is_attending' => false,
            'attendee_count' => $event->attendee_count,
        ], 'Left event.');
    }

    /**
     * Attendees who opted into networking for this event. Same privacy rules as
     * /nearby: opted-out attendees, non-discoverable users and blocked users
     * never appear, and contact details stay behind each subject's preferences.
     */
    public function attendees(Request $request, Event $event): JsonResponse
    {
        $this->authorize('viewAttendees', $event);

        return $this->respondWithAttendees($request, $event, []);
    }

    /** "Who's Mingle-ing?" -- the attendee list with networking filters applied. */
    public function networking(EventNetworkingRequest $request, Event $event): JsonResponse
    {
        $this->authorize('viewAttendees', $event);

        return $this->respondWithAttendees($request, $event, $request->filters());
    }

    /** @param  array<string, mixed>  $filters */
    private function respondWithAttendees(Request $request, Event $event, array $filters): JsonResponse
    {
        $viewer = $request->user();

        $results = $this->discovery->eventAttendees(
            $viewer,
            $event,
            $filters,
            $request->has('per_page') ? (int) $request->input('per_page') : null,
        );

        $this->discovery->creditDiscoveries($viewer, $results->getCollection()->pluck('id')->all(), $event->id);

        return ApiResponse::success([
            'items' => $this->discovery->present($results->getCollection(), $viewer, includePendingState: true),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
                'has_more' => $results->hasMorePages(),
            ],
        ]);
    }
}
