<?php

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price_cents' => 0,
                'billing_interval' => BillingInterval::Free->value,
                'features' => [
                    'discovery_radius_meters' => 500,
                    'daily_connection_requests' => 10,
                    'see_who_viewed_profile' => false,
                    'advanced_filters' => false,
                    'verification_badge_eligible' => false,
                    'event_creation' => true,
                    'priority_in_nearby' => false,
                ],
            ],
            [
                'name' => 'Premium Monthly',
                'slug' => 'premium-monthly',
                'price_cents' => 999,
                'billing_interval' => BillingInterval::Monthly->value,
                'features' => [
                    'discovery_radius_meters' => 5000,
                    'daily_connection_requests' => null,
                    'see_who_viewed_profile' => true,
                    'advanced_filters' => true,
                    'verification_badge_eligible' => true,
                    'event_creation' => true,
                    'priority_in_nearby' => true,
                ],
            ],
            [
                'name' => 'Premium Yearly',
                'slug' => 'premium-yearly',
                'price_cents' => 9599,
                'billing_interval' => BillingInterval::Yearly->value,
                'features' => [
                    'discovery_radius_meters' => 5000,
                    'daily_connection_requests' => null,
                    'see_who_viewed_profile' => true,
                    'advanced_filters' => true,
                    'verification_badge_eligible' => true,
                    'event_creation' => true,
                    'priority_in_nearby' => true,
                    'months_free' => 2,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
