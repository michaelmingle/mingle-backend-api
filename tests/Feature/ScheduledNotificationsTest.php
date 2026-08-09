<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Skill;
use App\Notifications\EventReminder;
use App\Notifications\NetworkingSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class ScheduledNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_reminders_only_reach_networking_enabled_attendees_in_the_reminder_window(): void
    {
        $event = Event::factory()->create(['starts_at' => now()->addHours(24), 'ends_at' => now()->addHours(30)]);
        $tooFarOut = Event::factory()->create(['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(6)]);

        $networking = $this->makeUser();
        $notNetworking = $this->makeUser();

        $event->eventAttendees()->create(['user_id' => $networking->id, 'is_networking_enabled' => true, 'joined_at' => now()]);
        $event->eventAttendees()->create(['user_id' => $notNetworking->id, 'is_networking_enabled' => false, 'joined_at' => now()]);
        $tooFarOut->eventAttendees()->create(['user_id' => $networking->id, 'is_networking_enabled' => true, 'joined_at' => now()]);

        $this->artisan('events:send-reminders')->assertSuccessful();

        $this->assertSame(1, $networking->notifications()->where('type', EventReminder::class)->count());
        $this->assertSame(0, $notNetworking->notifications()->count());
        $this->assertNotNull($event->fresh()->reminder_sent_at);
        $this->assertNull($tooFarOut->fresh()->reminder_sent_at);
    }

    public function test_event_reminders_are_not_sent_twice_for_the_same_event(): void
    {
        $event = Event::factory()->create(['starts_at' => now()->addHours(24), 'ends_at' => now()->addHours(30)]);
        $attendee = $this->makeUser();
        $event->eventAttendees()->create(['user_id' => $attendee->id, 'is_networking_enabled' => true, 'joined_at' => now()]);

        $this->artisan('events:send-reminders')->assertSuccessful();
        $this->artisan('events:send-reminders')->assertSuccessful();

        $this->assertSame(1, $attendee->notifications()->where('type', EventReminder::class)->count());
    }

    public function test_networking_suggestions_go_to_a_nearby_discoverable_user_sharing_a_skill(): void
    {
        $skill = Skill::factory()->create(['name' => 'Flutter']);

        // Accra-ish coordinates, ~200m apart -- well inside the default radius.
        $user = $this->makeUser([], ['is_discoverable' => true, 'latitude' => 5.6037, 'longitude' => -0.1870, 'discovery_radius_meters' => 2000]);
        $nearby = $this->makeUser([], ['is_discoverable' => true, 'latitude' => 5.6040, 'longitude' => -0.1873, 'discovery_radius_meters' => 2000]);
        $user->skills()->attach($skill);
        $nearby->skills()->attach($skill);

        // Discoverable but far away and with nothing in common -- should never surface.
        $this->makeUser([], ['is_discoverable' => true, 'latitude' => -1.2921, 'longitude' => 36.8219]);

        $this->artisan('networking:send-suggestions')->assertSuccessful();

        $notification = $user->notifications()->where('type', NetworkingSuggestion::class)->first();

        $this->assertNotNull($notification);
        $this->assertSame($nearby->id, $notification->data['user_id']);
        $this->assertContains('Flutter', $notification->data['reasons']);
    }

    public function test_networking_suggestions_skip_users_with_no_shared_skill_or_interest(): void
    {
        $this->makeUser([], ['is_discoverable' => true, 'latitude' => 5.6037, 'longitude' => -0.1870]);
        $this->makeUser([], ['is_discoverable' => true, 'latitude' => 5.6038, 'longitude' => -0.1871]);

        $this->artisan('networking:send-suggestions')->assertSuccessful();

        $this->assertSame(0, DatabaseNotification::query()->where('type', NetworkingSuggestion::class)->count());
    }

    public function test_networking_suggestions_are_not_repeated_within_the_dedupe_window(): void
    {
        $skill = Skill::factory()->create(['name' => 'Laravel']);
        $user = $this->makeUser([], ['is_discoverable' => true, 'latitude' => 5.6037, 'longitude' => -0.1870]);
        $nearby = $this->makeUser([], ['is_discoverable' => true, 'latitude' => 5.6038, 'longitude' => -0.1871]);
        $user->skills()->attach($skill);
        $nearby->skills()->attach($skill);

        $this->artisan('networking:send-suggestions')->assertSuccessful();
        $this->artisan('networking:send-suggestions')->assertSuccessful();

        $this->assertSame(1, $user->notifications()->where('type', NetworkingSuggestion::class)->count());
    }
}
