<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateInterestsRequest;
use App\Http\Requests\Profile\UpdatePrivacyRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UpdateSkillsRequest;
use App\Http\Requests\Profile\UploadAvatarRequest;
use App\Http\Resources\ContactPreferencesResource;
use App\Http\Resources\InterestResource;
use App\Http\Resources\SkillResource;
use App\Http\Resources\UserResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    public function show(Request $request): JsonResponse
    {
        $user = $this->hydrate($request);

        return $this->ok(new UserResource($user));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->profiles->bootstrapFor($user)->profile;

        if ($request->has('name')) {
            $user->update(['name' => $request->string('name')->toString()]);
        }

        $this->profiles->update($profile, $request->profileAttributes());

        return $this->ok(new UserResource($this->hydrate($request)), 'Profile updated.');
    }

    public function uploadPhoto(UploadAvatarRequest $request): JsonResponse
    {
        $profile = $this->profiles->bootstrapFor($request->user())->profile;

        $url = $this->profiles->storeAvatar($profile, $request->file('avatar'));

        return $this->ok(['avatar_url' => $url], 'Photo updated.');
    }

    public function completion(Request $request): JsonResponse
    {
        $profile = $this->profiles->bootstrapFor($request->user())->profile;

        $breakdown = $profile->completionBreakdown();
        $profile->recalculateCompletion();

        return $this->ok($breakdown);
    }

    public function updateSkills(UpdateSkillsRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->skills()->sync($request->input('skill_ids', []));

        $this->profiles->bootstrapFor($user)->profile->recalculateCompletion();

        return $this->ok(
            ['skills' => SkillResource::collection($user->load('skills')->skills)->resolve($request)],
            'Skills updated.'
        );
    }

    public function updateInterests(UpdateInterestsRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->interests()->sync($request->input('interest_ids', []));

        $this->profiles->bootstrapFor($user)->profile->recalculateCompletion();

        return $this->ok(
            ['interests' => InterestResource::collection($user->load('interests')->interests)->resolve($request)],
            'Interests updated.'
        );
    }

    public function updatePrivacy(UpdatePrivacyRequest $request): JsonResponse
    {
        $preferences = $this->profiles->bootstrapFor($request->user())->contactPreferences;

        $preferences->fill($request->validated())->save();

        return $this->ok(new ContactPreferencesResource($preferences->refresh()), 'Privacy settings updated.');
    }

    private function hydrate(Request $request)
    {
        return $this->profiles->bootstrapFor($request->user())
            ->load(['profile', 'contactPreferences', 'skills', 'interests']);
    }
}
