<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexUsersRequest;
use App\Http\Requests\Admin\ModerateUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    public function index(IndexUsersRequest $request): JsonResponse
    {
        $query = User::query()
            ->with('profile')
            ->withTrashed()
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->has('is_verified'), fn (Builder $q) => $q->where('is_verified', $request->boolean('is_verified')))
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $like = '%'.$request->input('search').'%';
                $q->where(fn (Builder $s) => $s->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->latest();

        return $this->paginated(AdminUserResource::collection($query->paginate($this->perPage())));
    }

    /** Grants or revokes the verification badge. */
    public function verify(ModerateUserRequest $request, User $user): JsonResponse
    {
        $user->update(['is_verified' => $request->has('value') ? $request->boolean('value') : true]);

        return $this->ok(new AdminUserResource($user->refresh()), 'Verification updated.');
    }

    public function suspend(ModerateUserRequest $request, User $user): JsonResponse
    {
        return $this->applyStatus($request, $user, UserStatus::Suspended, 'Account suspended.');
    }

    public function ban(ModerateUserRequest $request, User $user): JsonResponse
    {
        return $this->applyStatus($request, $user, UserStatus::Banned, 'Account banned.');
    }

    /** Passing `value: false` lifts the restriction and returns the user to active. */
    private function applyStatus(ModerateUserRequest $request, User $user, UserStatus $status, string $message): JsonResponse
    {
        $apply = $request->has('value') ? $request->boolean('value') : true;

        $user->update(['status' => $apply ? $status : UserStatus::Active]);

        if ($apply) {
            // A restricted account should not stay signed in on its devices.
            $user->tokens()->delete();
            $user->profile()->update(['is_discoverable' => false]);
        }

        return $this->ok(
            new AdminUserResource($user->refresh()),
            $apply ? $message : 'Account restored to active.'
        );
    }
}
