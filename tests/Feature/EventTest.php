<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Interest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_an_event_and_becomes_its_organizer(): void
    {
        $user = $this->actingAsUser();

        $response = $this->postJson('/api/events', [
            'title' => 'Accra Tech Summit 2026',
            'description' => 'Builders gathering.',
            'location_text' => 'Accra',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'ends_at' => now()->addWeek()->addDay()->toIso8601String(),
            'category' => 'Conference',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Accra Tech Summit 2026')
            ->assertJsonPath('data.is_organizer', true)
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('events', [
            'id' => $response->json('data.id'),
            'organizer_id' => $user->id,
            'slug' => 'accra-tech-summit-2026',
        ]);
    }

    public function test_event_creation_validates_the_date_range(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/events', [
            'title' => 'Backwards',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'ends_at' => now()->toIso8601String(),
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['ends_at']]);
    }

    public function test_only_the_organizer_can_update_or_delete_an_event(): void
    {
        $organizer = $this->makeUser();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);

        $this->actingAsUser();
        $this->putJson("/api/events/{$event->id}", ['title' => 'Hijacked'])->assertStatus(403);
        $this->deleteJson("/api/events/{$event->id}")->assertStatus(403);

        $this->actingAsUser($organizer);
        $this->putJson("/api/events/{$event->id}", ['title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed');
        $this->deleteJson("/api/events/{$event->id}")->assertOk();

        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_joining_an_event_records_attendance_and_updates_the_count(): void
    {
        $user = $this->actingAsUser();
        $event = Event::factory()->create();

        $this->postJson("/api/events/{$event->id}/join")
            ->assertOk()
            ->assertJsonPath('data.is_attending', true)
            ->assertJsonPath('data.is_networking_enabled', true)
            ->assertJsonPath('data.attendee_count', 1);

        $this->assertDatabaseHas('event_attendees', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'is_networking_enabled' => true,
        ]);

        $this->postJson("/api/events/{$event->id}/leave")
            ->assertOk()
            ->assertJsonPath('data.attendee_count', 0);

        $this->assertDatabaseCount('event_attendees', 0);
    }

    public function test_attendee_list_respects_the_networking_flag(): void
    {
        $viewer = $this->actingAsUser();
        $event = Event::factory()->create();

        $this->postJson("/api/events/{$event->id}/join")->assertOk();

        $networking = $this->makeUser(['name' => 'Open To Meet']);
        $optedOut = $this->makeUser(['name' => 'Heads Down']);

        $event->eventAttendees()->create([
            'user_id' => $networking->id, 'is_networking_enabled' => true, 'joined_at' => now(),
        ]);
        $event->eventAttendees()->create([
            'user_id' => $optedOut->id, 'is_networking_enabled' => false, 'joined_at' => now(),
        ]);

        $names = collect($this->getJson("/api/events/{$event->id}/attendees")->assertOk()->json('data.items'))
            ->pluck('name')->all();

        $this->assertSame(['Open To Meet'], $names);
        $this->assertNotContains($viewer->name, $names);
    }

    public function test_attendee_list_excludes_blocked_users_and_hides_contact_details(): void
    {
        $viewer = $this->actingAsUser();
        $event = Event::factory()->create();

        $blocked = $this->makeUser(['name' => 'Blocked']);
        $visible = $this->makeUser(['name' => 'Visible', 'phone' => '+233200000011'], [], [
            'phone_visibility' => 'everyone',
            'email_visibility' => 'everyone',
        ]);

        foreach ([$blocked, $visible] as $attendee) {
            $event->eventAttendees()->create([
                'user_id' => $attendee->id, 'is_networking_enabled' => true, 'joined_at' => now(),
            ]);
        }

        $viewer->blocks()->create(['blocked_user_id' => $blocked->id]);

        $items = $this->getJson("/api/events/{$event->id}/attendees")->assertOk()->json('data.items');

        $this->assertSame(['Visible'], collect($items)->pluck('name')->all());
        $this->assertArrayNotHasKey('contact', $items[0]);
    }

    public function test_networking_list_filters_by_looking_for_and_profession(): void
    {
        $this->actingAsUser();
        $event = Event::factory()->create();

        $mentor = $this->makeUser(['name' => 'Mentor'], [
            'looking_for' => ['mentorship'],
            'professional_title' => 'Engineering Manager',
            'industry' => 'Technology',
        ]);
        $hiring = $this->makeUser(['name' => 'Recruiter'], [
            'looking_for' => ['employment'],
            'professional_title' => 'Talent Partner',
            'industry' => 'Media',
        ]);

        foreach ([$mentor, $hiring] as $attendee) {
            $event->eventAttendees()->create([
                'user_id' => $attendee->id, 'is_networking_enabled' => true, 'joined_at' => now(),
            ]);
        }

        $byLookingFor = $this->getJson("/api/events/{$event->id}/networking?looking_for=mentorship")
            ->assertOk()->json('data.items');
        $this->assertSame(['Mentor'], collect($byLookingFor)->pluck('name')->all());

        $byProfession = $this->getJson("/api/events/{$event->id}/networking?profession=Talent")
            ->assertOk()->json('data.items');
        $this->assertSame(['Recruiter'], collect($byProfession)->pluck('name')->all());

        $byIndustry = $this->getJson("/api/events/{$event->id}/networking?industry=Technology")
            ->assertOk()->json('data.items');
        $this->assertSame(['Mentor'], collect($byIndustry)->pluck('name')->all());
    }

    public function test_networking_list_surfaces_shared_interests_first(): void
    {
        $tech = Interest::factory()->create(['name' => 'Technology']);

        $viewer = $this->actingAsUser();
        $viewer->interests()->sync([$tech->id]);

        $event = Event::factory()->create();

        $match = $this->makeUser(['name' => 'Alpha Match']);
        $match->interests()->sync([$tech->id]);
        $other = $this->makeUser(['name' => 'Aardvark']);

        foreach ([$match, $other] as $attendee) {
            $event->eventAttendees()->create([
                'user_id' => $attendee->id, 'is_networking_enabled' => true, 'joined_at' => now(),
            ]);
        }

        $items = $this->getJson("/api/events/{$event->id}/networking")->assertOk()->json('data.items');

        // Ordered by shared interests despite "Aardvark" sorting first by name.
        $this->assertSame('Alpha Match', $items[0]['name']);
        $this->assertSame(['Technology'], $items[0]['shared_interests']);
    }

    public function test_event_listing_supports_search_category_and_upcoming_filters(): void
    {
        $this->actingAsUser();

        Event::factory()->create(['title' => 'Fintech Forum', 'category' => 'Conference']);
        Event::factory()->create(['title' => 'Design Jam', 'category' => 'Workshop']);
        Event::factory()->past()->create(['title' => 'Old Meetup', 'category' => 'Meetup']);

        $this->assertCount(3, $this->getJson('/api/events')->assertOk()->json('data.items'));

        $search = $this->getJson('/api/events?search=Fintech')->json('data.items');
        $this->assertSame('Fintech Forum', $search[0]['title']);

        $byCategory = $this->getJson('/api/events?category=Workshop')->json('data.items');
        $this->assertSame('Design Jam', $byCategory[0]['title']);

        $upcoming = collect($this->getJson('/api/events?upcoming=1')->json('data.items'))->pluck('title')->all();
        $this->assertNotContains('Old Meetup', $upcoming);
    }

    public function test_draft_events_are_only_visible_to_their_organizer(): void
    {
        $organizer = $this->makeUser();
        $draft = Event::factory()->draft()->create(['organizer_id' => $organizer->id, 'title' => 'Secret Plan']);

        $this->actingAsUser();
        $this->assertCount(0, $this->getJson('/api/events')->json('data.items'));
        $this->getJson("/api/events/{$draft->id}")->assertStatus(403);

        $this->actingAsUser($organizer);
        $this->assertCount(1, $this->getJson('/api/events')->json('data.items'));
        $this->getJson("/api/events/{$draft->id}")->assertOk();
    }
}
