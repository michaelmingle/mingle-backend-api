<?php

namespace Tests;

use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /** A user with the profile + contact preference rows registration creates. */
    protected function makeUser(array $attributes = [], array $profile = [], array $preferences = []): User
    {
        $user = User::factory()->create($attributes);

        app(ProfileService::class)->bootstrapFor($user);

        if ($profile !== []) {
            $user->profile->fill($profile)->save();
        }

        if ($preferences !== []) {
            $user->contactPreferences->fill($preferences)->save();
        }

        return $user->refresh();
    }

    protected function actingAsUser(?User $user = null): User
    {
        $user ??= $this->makeUser();

        Sanctum::actingAs($user);

        return $user;
    }
}
