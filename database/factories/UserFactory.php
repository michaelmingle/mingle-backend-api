<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\ContactSharingPreference;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'auth_provider' => 'email',
            'is_admin' => false,
            'is_verified' => false,
            'is_premium' => false,
            'status' => UserStatus::Active,
            'last_active_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['is_admin' => true]);
    }

    public function verifiedBadge(): static
    {
        return $this->state(fn () => ['is_verified' => true]);
    }

    public function premium(): static
    {
        return $this->state(fn () => ['is_premium' => true]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Suspended]);
    }

    public function banned(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Banned]);
    }

    /**
     * Gives the user the profile + contact preference rows that registration
     * would have created, so tests do not have to build them by hand.
     */
    public function withProfile(array $profile = [], array $preferences = []): static
    {
        return $this->afterCreating(function (User $user) use ($profile, $preferences) {
            Profile::factory()->for($user)->create($profile);
            ContactSharingPreference::factory()->for($user)->create($preferences);
        });
    }

    /** Discoverable at a specific point -- the common setup for /nearby tests. */
    public function discoverableAt(float $latitude, float $longitude, int $radiusMeters = 5000): static
    {
        return $this->withProfile([
            'is_discoverable' => true,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'discovery_radius_meters' => $radiusMeters,
        ]);
    }
}
