<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\SkillResource;
use App\Models\Event;
use App\Models\Skill;
use App\Models\User;
use App\Services\DiscoveryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __construct(private readonly DiscoveryService $discovery) {}

    /**
     * Unified search. `type=all` returns a capped slice of each bucket; a single
     * type returns a full paginated set for that bucket.
     */
    public function __invoke(SearchRequest $request): JsonResponse
    {
        $viewer = $request->user();
        $term = $request->string('q')->toString();
        $type = $request->input('type', 'all');
        $perPage = $this->perPage();

        if ($type === 'all') {
            return $this->ok([
                'query' => $term,
                'people' => $this->discovery->present(
                    $this->peopleQuery($viewer, $term)->limit(5)->get(),
                    $viewer,
                    includePendingState: true,
                ),
                'events' => EventResource::collection($this->eventsQuery($viewer, $term)->limit(5)->get())->toArray($request),
                'skills' => SkillResource::collection($this->skillsQuery($term)->limit(5)->get())->toArray($request),
            ]);
        }

        return match ($type) {
            'people' => $this->respondWithPeople($request, $viewer, $term, $perPage),
            'events' => $this->paginated(EventResource::collection($this->eventsQuery($viewer, $term)->paginate($perPage))),
            default => $this->paginated(SkillResource::collection($this->skillsQuery($term)->paginate($perPage))),
        };
    }

    private function respondWithPeople(SearchRequest $request, User $viewer, string $term, int $perPage): JsonResponse
    {
        $results = $this->peopleQuery($viewer, $term)->paginate($perPage);

        return $this->ok([
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

    /**
     * People search intentionally ignores `is_discoverable` -- that flag governs
     * proximity broadcasting, not whether a professional profile can be looked
     * up by name. Blocks and inactive accounts are still excluded.
     */
    private function peopleQuery(User $viewer, string $term): Builder
    {
        $like = '%'.$term.'%';

        return User::query()
            ->select('users.*')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->where('users.id', '!=', $viewer->id)
            ->where('users.status', UserStatus::Active->value)
            ->notBlockedWith($viewer->id)
            ->where(function (Builder $q) use ($like) {
                $q->where('users.name', 'like', $like)
                    ->orWhere('profiles.professional_title', 'like', $like)
                    ->orWhere('profiles.industry', 'like', $like)
                    ->orWhere('profiles.bio', 'like', $like);
            })
            ->with(['profile', 'contactPreferences', 'skills', 'interests'])
            ->orderBy('users.name');
    }

    private function eventsQuery(User $viewer, string $term): Builder
    {
        return Event::query()
            ->with('organizer')
            ->search($term)
            ->where(function (Builder $q) use ($viewer) {
                $q->where('status', EventStatus::Published->value)
                    ->orWhere('organizer_id', $viewer->id);
            })
            ->orderByRaw('starts_at is null')
            ->orderBy('starts_at');
    }

    private function skillsQuery(string $term): Builder
    {
        return Skill::query()
            ->where('name', 'like', '%'.$term.'%')
            ->orderBy('name');
    }
}
