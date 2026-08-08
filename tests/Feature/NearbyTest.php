<?php

namespace Tests\Feature;

use App\Models\Interest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NearbyTest extends TestCase
{
    use RefreshDatabase;

    /** Accra city centre -- the reference point for every case below. */
    private const LAT = 5.6037;

    private const LNG = -0.1870;

    public function test_discoverability_toggle_updates_the_profile_and_opens_a_session(): void
    {
        $user = $this->actingAsUser();

        $this->putJson('/api/nearby/discoverability', [
            'is_discoverable' => true,
            'discovery_radius_meters' => 1500,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ])->assertOk()
            ->assertJsonPath('data.is_discoverable', true)
            ->assertJsonPath('data.discovery_radius_meters', 1500)
            ->assertJsonPath('data.networking_session.ended_at', null);

        $profile = $user->fresh()->profile;
        $this->assertTrue($profile->is_discoverable);
        $this->assertSame(1500, $profile->discovery_radius_meters);
        $this->assertDatabaseCount('networking_sessions', 1);
    }

    public function test_turning_discoverability_off_closes_the_session(): void
    {
        $user = $this->actingAsUser();

        $this->putJson('/api/nearby/discoverability', [
            'is_discoverable' => true,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ])->assertOk();

        $this->putJson('/api/nearby/discoverability', ['is_discoverable' => false])
            ->assertOk()
            ->assertJsonPath('data.is_discoverable', false);

        $this->assertFalse($user->fresh()->profile->is_discoverable);
        $this->assertNotNull($user->networkingSessions()->first()->ended_at);
    }

    public function test_discovery_radius_is_capped(): void
    {
        $this->actingAsUser();

        $this->putJson('/api/nearby/discoverability', [
            'is_discoverable' => true,
            'discovery_radius_meters' => 10_000_000,
        ])->assertStatus(422);
    }

    public function test_nearby_returns_discoverable_users_and_excludes_the_rest(): void
    {
        $viewer = $this->actingAsUser(
            $this->makeUser(['name' => 'Viewer'], [
                'is_discoverable' => true,
                'latitude' => self::LAT,
                'longitude' => self::LNG,
                'discovery_radius_meters' => 5000,
            ])
        );

        $close = $this->makeUser(['name' => 'Close By'], [
            'is_discoverable' => true,
            'latitude' => self::LAT + 0.001,
            'longitude' => self::LNG + 0.001,
        ]);

        // Excluded: too far away (about 200 km).
        $this->makeUser(['name' => 'Far Away'], [
            'is_discoverable' => true,
            'latitude' => 6.69,
            'longitude' => -1.62,
        ]);

        // Excluded: has not turned discoverability on.
        $this->makeUser(['name' => 'Hidden'], [
            'is_discoverable' => false,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        // Excluded: suspended.
        $this->makeUser(['name' => 'Suspended', 'status' => 'suspended'], [
            'is_discoverable' => true,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        $response = $this->getJson('/api/nearby?lat='.self::LAT.'&lng='.self::LNG)->assertOk();

        $names = collect($response->json('data.items'))->pluck('name')->all();

        $this->assertSame(['Close By'], $names);
        $this->assertNotContains($viewer->name, $names);
        $this->assertIsFloat($response->json('data.items.0.distance_meters') + 0.0);
        $this->assertLessThan(300, $response->json('data.items.0.distance_meters'));
    }

    public function test_nearby_never_leaks_contact_details(): void
    {
        $this->actingAsUser($this->makeUser([], [
            'is_discoverable' => true,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'discovery_radius_meters' => 5000,
        ]));

        // Even a user who shares everything must not have contact details
        // broadcast through the proximity feed.
        $this->makeUser(['name' => 'Open Book', 'phone' => '+233200000001'], [
            'is_discoverable' => true,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ], [
            'email_visibility' => 'everyone',
            'phone_visibility' => 'everyone',
        ]);

        $item = $this->getJson('/api/nearby?lat='.self::LAT.'&lng='.self::LNG)
            ->assertOk()
            ->json('data.items.0');

        $this->assertArrayNotHasKey('contact', $item);
        $this->assertArrayNotHasKey('email', $item);
        $this->assertArrayNotHasKey('phone', $item);
        $this->assertArrayNotHasKey('password', $item);
    }

    public function test_nearby_excludes_users_blocked_in_either_direction(): void
    {
        $viewer = $this->actingAsUser($this->makeUser(['name' => 'Viewer'], [
            'is_discoverable' => true,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'discovery_radius_meters' => 5000,
        ]));

        $blockedByViewer = $this->makeUser(['name' => 'Blocked'], [
            'is_discoverable' => true, 'latitude' => self::LAT, 'longitude' => self::LNG,
        ]);
        $blockerOfViewer = $this->makeUser(['name' => 'Blocker'], [
            'is_discoverable' => true, 'latitude' => self::LAT, 'longitude' => self::LNG,
        ]);
        $this->makeUser(['name' => 'Neutral'], [
            'is_discoverable' => true, 'latitude' => self::LAT, 'longitude' => self::LNG,
        ]);

        $viewer->blocks()->create(['blocked_user_id' => $blockedByViewer->id]);
        $blockerOfViewer->blocks()->create(['blocked_user_id' => $viewer->id]);

        $names = collect(
            $this->getJson('/api/nearby?lat='.self::LAT.'&lng='.self::LNG)->assertOk()->json('data.items')
        )->pluck('name')->all();

        $this->assertSame(['Neutral'], $names);
    }

    public function test_nearby_reports_shared_interests_and_can_sort_by_them(): void
    {
        $tech = Interest::factory()->create(['name' => 'Technology']);
        $design = Interest::factory()->create(['name' => 'Design']);

        $viewer = $this->actingAsUser($this->makeUser([], [
            'is_discoverable' => true,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'discovery_radius_meters' => 5000,
        ]));
        $viewer->interests()->sync([$tech->id, $design->id]);

        // Nearer, but shares nothing.
        $this->makeUser(['name' => 'No Overlap'], [
            'is_discoverable' => true, 'latitude' => self::LAT, 'longitude' => self::LNG,
        ]);

        // Further away, but shares both interests.
        $match = $this->makeUser(['name' => 'Good Match'], [
            'is_discoverable' => true,
            'latitude' => self::LAT + 0.01,
            'longitude' => self::LNG + 0.01,
        ]);
        $match->interests()->sync([$tech->id, $design->id]);

        $items = $this->getJson('/api/nearby?lat='.self::LAT.'&lng='.self::LNG.'&sort=shared_interests')
            ->assertOk()->json('data.items');

        $this->assertSame('Good Match', $items[0]['name']);
        $this->assertEqualsCanonicalizing(['Technology', 'Design'], $items[0]['shared_interests']);
        $this->assertSame([], $items[1]['shared_interests']);

        // Default sort is by proximity, so the ordering flips back.
        $closest = $this->getJson('/api/nearby?lat='.self::LAT.'&lng='.self::LNG.'&sort=closest')
            ->assertOk()->json('data.items');

        $this->assertSame('No Overlap', $closest[0]['name']);
    }

    public function test_nearby_can_filter_by_profession(): void
    {
        $this->actingAsUser($this->makeUser([], [
            'is_discoverable' => true,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'discovery_radius_meters' => 5000,
        ]));

        $this->makeUser(['name' => 'Designer'], [
            'is_discoverable' => true, 'latitude' => self::LAT, 'longitude' => self::LNG,
            'professional_title' => 'Product Designer',
        ]);
        $this->makeUser(['name' => 'Engineer'], [
            'is_discoverable' => true, 'latitude' => self::LAT, 'longitude' => self::LNG,
            'professional_title' => 'Backend Engineer',
        ]);

        $names = collect(
            $this->getJson('/api/nearby?lat='.self::LAT.'&lng='.self::LNG.'&profession=Designer')
                ->assertOk()->json('data.items')
        )->pluck('name')->all();

        $this->assertSame(['Designer'], $names);
    }

    public function test_nearby_requires_coordinates(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/nearby')->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lat', 'lng']]);
    }
}
