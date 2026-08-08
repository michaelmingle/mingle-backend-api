<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Notifications\ConnectionRequestReceived;
use App\Notifications\EventReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_connection_request_lands_in_the_receivers_notification_feed(): void
    {
        $sender = $this->makeUser(['name' => 'Sender']);
        $receiver = $this->makeUser();

        $this->actingAsUser($sender);
        $this->postJson("/api/users/{$receiver->id}/connect")->assertCreated();

        $this->actingAsUser($receiver);
        $response = $this->getJson('/api/notifications')->assertOk();

        $this->assertSame(1, $response->json('data.unread_count'));
        $this->assertSame('connection_request_received', $response->json('data.items.0.type'));
        $this->assertSame($sender->id, $response->json('data.items.0.data.sender_id'));
        $this->assertFalse($response->json('data.items.0.is_read'));
    }

    public function test_notifications_can_be_marked_read_individually_and_in_bulk(): void
    {
        $user = $this->actingAsUser();
        $event = Event::factory()->create();

        $user->notify(new EventReminder($event));
        $user->notify(new EventReminder($event));

        $id = $this->getJson('/api/notifications')->json('data.items.0.id');

        $this->putJson("/api/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertSame(1, $this->getJson('/api/notifications')->json('data.unread_count'));

        $this->putJson('/api/notifications/read-all')->assertOk();

        $this->assertSame(0, $this->getJson('/api/notifications')->json('data.unread_count'));
    }

    public function test_a_user_cannot_mark_someone_elses_notification_as_read(): void
    {
        $owner = $this->makeUser();
        $owner->notify(new EventReminder(Event::factory()->create()));

        $id = $owner->notifications()->first()->id;

        $this->actingAsUser();
        $this->putJson("/api/notifications/{$id}/read")->assertStatus(404);
    }

    public function test_notification_payloads_carry_a_stable_type_key(): void
    {
        $receiver = $this->makeUser();
        $this->actingAsUser($this->makeUser());
        $this->postJson("/api/users/{$receiver->id}/connect")->assertCreated();

        $data = $receiver->notifications()->first()->data;

        $this->assertSame('connection_request_received', $data['type']);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertSame(ConnectionRequestReceived::class, $receiver->notifications()->first()->type);
    }
}
