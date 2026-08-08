<?php

namespace Tests\Unit;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The nearby feed leans entirely on raw SQL trigonometry, so it is worth
 * pinning the maths down against known distances -- and proving the expression
 * actually evaluates on SQLite, which is what the suite runs on.
 */
class HaversineDistanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_php_distance_matches_a_known_separation(): void
    {
        $profile = Profile::factory()->for(User::factory())->at(5.6037, -0.1870)->create();

        // Accra -> Tema is roughly 24 km.
        $meters = $profile->distanceTo(5.6698, -0.0166);

        $this->assertGreaterThan(18000, $meters);
        $this->assertLessThan(30000, $meters);
    }

    public function test_identical_coordinates_produce_zero_and_not_nan(): void
    {
        $profile = Profile::factory()->for(User::factory())->at(5.6037, -0.1870)->create();

        $this->assertSame(0.0, round($profile->distanceTo(5.6037, -0.1870), 6));
    }

    public function test_sql_expression_agrees_with_the_php_implementation(): void
    {
        Profile::factory()->for(User::factory())->at(5.6037, -0.1870)->discoverable()->create();

        $row = Profile::query()->withDistance(5.6100, -0.1900)->first();
        $expected = $row->distanceTo(5.6100, -0.1900);

        $this->assertEqualsWithDelta($expected, (float) $row->distance_meters, 0.5);
    }

    public function test_discoverable_nearby_scope_filters_by_radius(): void
    {
        Profile::factory()->for(User::factory())->at(5.6037, -0.1870)->discoverable()->create();
        Profile::factory()->for(User::factory())->at(6.6900, -1.6200)->discoverable()->create();
        Profile::factory()->for(User::factory())->at(5.6040, -0.1872)->create(); // not discoverable

        $results = Profile::query()->discoverableNearby(5.6037, -0.1870, 10)->get();

        $this->assertCount(1, $results);
        $this->assertLessThan(1000, (float) $results->first()->distance_meters);
    }
}
