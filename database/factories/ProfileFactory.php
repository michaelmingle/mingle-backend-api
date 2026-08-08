<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Profile> */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'avatar_url' => null,
            'professional_title' => fake()->jobTitle(),
            'bio' => fake()->sentences(2, true),
            'industry' => fake()->randomElement([
                'Technology', 'Finance', 'Healthcare', 'Education', 'Media', 'Design',
            ]),
            'location_text' => fake()->city(),
            'latitude' => null,
            'longitude' => null,
            'is_discoverable' => false,
            'discovery_radius_meters' => (int) config('mingle.discovery.default_radius_meters', 500),
            'looking_for' => fake()->randomElements(
                ['networking', 'business', 'employment', 'mentorship', 'clients', 'collaboration', 'friends'],
                2
            ),
            'profile_completion_percent' => 0,
        ];
    }

    public function discoverable(): static
    {
        return $this->state(fn () => ['is_discoverable' => true]);
    }

    public function at(float $latitude, float $longitude): static
    {
        return $this->state(fn () => ['latitude' => $latitude, 'longitude' => $longitude]);
    }
}
