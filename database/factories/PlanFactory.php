<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' Plan';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'price_cents' => fake()->randomElement([0, 999, 9999]),
            'billing_interval' => BillingInterval::Monthly,
            'features' => ['unlimited_connections' => true],
        ];
    }
}
