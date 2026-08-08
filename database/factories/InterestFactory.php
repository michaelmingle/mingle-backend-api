<?php

namespace Database\Factories;

use App\Models\Interest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Interest> */
class InterestFactory extends Factory
{
    protected $model = Interest::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'category' => fake()->randomElement(['Professional', 'Lifestyle', 'Technology']),
        ];
    }
}
