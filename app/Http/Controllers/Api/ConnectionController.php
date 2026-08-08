<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Connections\IndexConnectionsRequest;
use App\Http\Requests\Connections\UpdateFavoriteRequest;
use App\Http\Requests\Connections\UpdateNoteRequest;
use App\Http\Resources\ConnectionResource;
use App\Models\Connection;
use App\Services\ConnectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectionController extends Controller
{
    public function __construct(private readonly ConnectionService $connections) {}

    public function index(IndexConnectionsRequest $request): JsonResponse
    {
        $user = $request->user();
        $tab = $request->input('tab', 'all');
        $search = $request->input('search');

        $query = Connection::query()
            ->forUser($user->id)
            ->with([
                'userOne.profile', 'userOne.contactPreferences',
                'userTwo.profile', 'userTwo.contactPreferences',
                'event', 'notes', 'favorites',
            ]);

        match ($tab) {
            'recent' => $query->latest('connected_at'),
            'events' => $query->fromEvents()->latest('connected_at'),
            'favorites' => $query->favoritedBy($user->id)->latest('connected_at'),
            default => $query->latest('connected_at'),
        };

        if (filled($search)) {
            // Search the counterpart's name/title, never the viewer's own record.
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($user, $like) {
                $q->whereHas('userOne', fn (Builder $u) => $u->where('users.id', '!=', $user->id)->where('name', 'like', $like))
                    ->orWhereHas('userTwo', fn (Builder $u) => $u->where('users.id', '!=', $user->id)->where('name', 'like', $like))
                    ->orWhereHas('userOne.profile', fn (Builder $p) => $p->where('user_id', '!=', $user->id)->where('professional_title', 'like', $like))
                    ->orWhereHas('userTwo.profile', fn (Builder $p) => $p->where('user_id', '!=', $user->id)->where('professional_title', 'like', $like));
            });
        }

        return $this->paginated(ConnectionResource::collection($query->paginate($this->perPage())));
    }

    public function show(Request $request, Connection $connection): JsonResponse
    {
        $this->authorize('view', $connection);

        $connection->load([
            'userOne.profile', 'userOne.contactPreferences', 'userOne.skills', 'userOne.interests',
            'userTwo.profile', 'userTwo.contactPreferences', 'userTwo.skills', 'userTwo.interests',
            'event', 'notes', 'favorites',
        ]);

        return $this->ok(new ConnectionResource($connection));
    }

    public function destroy(Request $request, Connection $connection): JsonResponse
    {
        $this->authorize('delete', $connection);

        $this->connections->disconnect($connection);

        return $this->ok(null, 'Connection removed.');
    }

    public function favorite(UpdateFavoriteRequest $request, Connection $connection): JsonResponse
    {
        $this->authorize('favorite', $connection);

        $userId = $request->user()->id;

        if ($request->boolean('favorite')) {
            $connection->favorites()->firstOrCreate(['user_id' => $userId]);
        } else {
            $connection->favorites()->where('user_id', $userId)->delete();
        }

        return $this->ok(
            ['is_favorite' => $connection->isFavoritedBy($userId)],
            $request->boolean('favorite') ? 'Added to favorites.' : 'Removed from favorites.'
        );
    }

    /** Private note -- each participant reads and writes only their own. */
    public function note(UpdateNoteRequest $request, Connection $connection): JsonResponse
    {
        $this->authorize('note', $connection);

        $userId = $request->user()->id;
        $note = $request->input('note');

        if (blank($note)) {
            $connection->notes()->where('owner_id', $userId)->delete();

            return $this->ok(['note' => null], 'Note cleared.');
        }

        $record = $connection->notes()->updateOrCreate(
            ['owner_id' => $userId],
            ['note' => $note],
        );

        return $this->ok(['note' => $record->note], 'Note saved.');
    }
}
