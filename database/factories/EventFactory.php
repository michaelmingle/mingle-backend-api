<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $title = ucwords(fake()->words(3, true)).' Summit';
        $startsAt = fake()->dateTimeBetween('+1 day', '+3 months');

        return [
            'organizer_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'description' => fake()->paragraph(),
            'location_text' => fake()->city(),
            'latitude' => null,
            'longitude' => null,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+8 hours'),
            'category' => fake()->randomElement(['Conference', 'Meetup', 'Workshop', 'Hackathon']),
            'status' => EventStatus::Published,
            'attendee_count' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Draft]);
    }

    public function past(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subWeek()->addHours(8),
        ]);
    }
}
