<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Connections\StoreConnectionRequestRequest;
use App\Http\Requests\Safety\ReportUserRequest;
use App\Http\Resources\ConnectionRequestResource;
use App\Http\Resources\PublicUserResource;
use App\Models\ConnectionRequest;
use App\Models\ProfileView;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ProfileViewed;
use App\Services\ConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly ConnectionService $connections) {}

    /**
     * Public profile of another user, filtered through their contact sharing
     * preferences. Viewing somebody else records a profile_views row (and, at
     * most once a day, notifies them).
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $viewer = $request->user();

        $this->authorize('view', $user);

        $user->load(['profile', 'contactPreferences', 'skills', 'interests']);

        $isConnected = $viewer->isConnectedWith($user);

        if ($viewer->id !== $user->id) {
            $this->recordView($viewer, $user);
        }

        $shared = $viewer->interests()->pluck('interests.name', 'interests.id');
        $sharedNames = $user->interests->pluck('name', 'id')->intersectByKeys($shared)->values()->all();

        $distance = null;
        if ($viewer->profile?->hasCoordinates() && $user->profile?->hasCoordinates()) {
            $distance = $user->profile->distanceTo($viewer->profile->latitude, $viewer->profile->longitude);
        }

        $resource = (new PublicUserResource($user))
            ->connected($isConnected)
            ->sharedInterests($sharedNames)
            ->distance($distance)
            ->pendingRequest(
                ConnectionRequest::query()
                    ->pendingBetween($viewer->id, $user->id)
                    ->exists()
            );

        return $this->ok($resource->resolve($request));
    }

    public function connect(StoreConnectionRequestRequest $request, User $user): JsonResponse
    {
        $this->authorize('connect', $user);

        $connectionRequest = $this->connections->sendRequest(
            $request->user(),
            $user,
            $request->input('message'),
            $request->input('event_id') !== null ? (int) $request->input('event_id') : null,
        );

        return $this->created(
            new ConnectionRequestResource($connectionRequest->load(['sender', 'receiver', 'event'])),
            'Connection request sent.'
        );
    }

    public function block(Request $request, User $user): JsonResponse
    {
        $this->authorize('block', $user);

        $request->user()->blocks()->firstOrCreate(['blocked_user_id' => $user->id]);

        return $this->ok(['blocked_user_id' => $user->id], 'User blocked.');
    }

    public function unblock(Request $request, User $user): JsonResponse
    {
        $request->user()->blocks()->where('blocked_user_id', $user->id)->delete();

        return $this->ok(['blocked_user_id' => $user->id], 'User unblocked.');
    }

    public function report(ReportUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('report', $user);

        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'reported_user_id' => $user->id,
            'reason' => $request->string('reason')->toString(),
            'details' => $request->input('details'),
        ]);

        return $this->created(['report_id' => $report->id], 'Report submitted.');
    }

    private function recordView(User $viewer, User $viewed): void
    {
        $recent = ProfileView::query()
            ->where('viewer_id', $viewer->id)
            ->where('viewed_user_id', $viewed->id)
            ->where('viewed_at', '>=', now()->subDay())
            ->exists();

        ProfileView::create([
            'viewer_id' => $viewer->id,
            'viewed_user_id' => $viewed->id,
            'viewed_at' => now(),
        ]);

        // Throttled to one notification per viewer per day so a browsing session
        // does not spam the subject.
        if (! $recent) {
            $viewed->notify(new ProfileViewed($viewer));
        }
    }
}
