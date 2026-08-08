<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Event;
use App\Models\User;
use App\Notifications\ConnectionRequestAccepted;
use App\Notifications\ConnectionRequestReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_send_a_connection_request(): void
    {
        Notification::fake();

        $sender = $this->actingAsUser();
        $receiver = $this->makeUser();

        $this->postJson("/api/users/{$receiver->id}/connect", ['message' => 'Great to meet you.'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.direction', 'outgoing');

        $this->assertDatabaseHas('connection_requests', [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'status' => 'pending',
        ]);

        Notification::assertSentTo($receiver, ConnectionRequestReceived::class);
    }

    public function test_a_user_cannot_connect_with_themselves(): void
    {
        $user = $this->actingAsUser();

        $this->postJson("/api/users/{$user->id}/connect")->assertStatus(403);
    }

    public function test_duplicate_pending_requests_are_rejected(): void
    {
        $this->actingAsUser();
        $receiver = $this->makeUser();

        $this->postJson("/api/users/{$receiver->id}/connect")->assertCreated();
        $this->postJson("/api/users/{$receiver->id}/connect")->assertStatus(422);
    }

    public function test_accepting_a_request_creates_a_canonical_connection(): void
    {
        Notification::fake();

        $sender = $this->makeUser(['name' => 'Sender']);
        $receiver = $this->makeUser(['name' => 'Receiver']);

        $this->actingAsUser($sender);
        $requestId = $this->postJson("/api/users/{$receiver->id}/connect")->json('data.id');

        $this->actingAsUser($receiver);
        $this->postJson("/api/connections/requests/{$requestId}/accept")
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Sender')
            ->assertJsonPath('data.user.is_connected', true);

        $this->assertDatabaseHas('connection_requests', ['id' => $requestId, 'status' => 'accepted']);

        [$one, $two] = Connection::canonicalPair($sender->id, $receiver->id);
        $this->assertDatabaseHas('connections', ['user_one_id' => $one, 'user_two_id' => $two]);

        Notification::assertSentTo($sender, ConnectionRequestAccepted::class);

        // And the connection now shows up for both sides.
        $this->getJson('/api/connections')->assertOk()->assertJsonPath('data.items.0.user.name', 'Sender');

        $this->actingAsUser($sender);
        $this->getJson('/api/connections')->assertOk()->assertJsonPath('data.items.0.user.name', 'Receiver');
    }

    public function test_only_the_receiver_can_accept_a_request(): void
    {
        $sender = $this->makeUser();
        $receiver = $this->makeUser();
        $bystander = $this->makeUser();

        $this->actingAsUser($sender);
        $requestId = $this->postJson("/api/users/{$receiver->id}/connect")->json('data.id');

        $this->actingAsUser($sender);
        $this->postJson("/api/connections/requests/{$requestId}/accept")->assertStatus(403);

        $this->actingAsUser($bystander);
        $this->postJson("/api/connections/requests/{$requestId}/accept")->assertStatus(403);
    }

    public function test_a_request_cannot_be_accepted_twice(): void
    {
        $sender = $this->makeUser();
        $receiver = $this->makeUser();

        $this->actingAsUser($sender);
        $requestId = $this->postJson("/api/users/{$receiver->id}/connect")->json('data.id');

        $this->actingAsUser($receiver);
        $this->postJson("/api/connections/requests/{$requestId}/accept")->assertOk();
        $this->postJson("/api/connections/requests/{$requestId}/accept")->assertStatus(422);
    }

    public function test_declining_a_request_leaves_no_connection(): void
    {
        $sender = $this->makeUser();
        $receiver = $this->makeUser();

        $this->actingAsUser($sender);
        $requestId = $this->postJson("/api/users/{$receiver->id}/connect")->json('data.id');

        $this->actingAsUser($receiver);
        $this->postJson("/api/connections/requests/{$requestId}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');

        $this->assertDatabaseCount('connections', 0);
    }

    public function test_incoming_and_outgoing_request_lists_are_separate(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();

        $this->actingAsUser($a);
        $this->postJson("/api/users/{$b->id}/connect")->assertCreated();

        $this->assertCount(1, $this->getJson('/api/connections/requests?direction=outgoing')->json('data.items'));
        $this->assertCount(0, $this->getJson('/api/connections/requests?direction=incoming')->json('data.items'));

        $this->actingAsUser($b);
        $this->assertCount(1, $this->getJson('/api/connections/requests?direction=incoming')->json('data.items'));
        $this->assertCount(0, $this->getJson('/api/connections/requests?direction=outgoing')->json('data.items'));
    }

    public function test_connection_tabs_filter_correctly(): void
    {
        $user = $this->actingAsUser();
        $event = Event::factory()->create();

        $plain = $this->connect($user, $this->makeUser(['name' => 'Plain']));
        $atEvent = $this->connect($user, $this->makeUser(['name' => 'From Event']), $event->id);
        $fav = $this->connect($user, $this->makeUser(['name' => 'Favorited']));

        $this->putJson("/api/connections/{$fav->id}/favorite", ['favorite' => true])->assertOk();

        $this->assertCount(3, $this->getJson('/api/connections?tab=all')->json('data.items'));

        $events = $this->getJson('/api/connections?tab=events')->json('data.items');
        $this->assertCount(1, $events);
        $this->assertSame('From Event', $events[0]['user']['name']);

        $favorites = $this->getJson('/api/connections?tab=favorites')->json('data.items');
        $this->assertCount(1, $favorites);
        $this->assertSame('Favorited', $favorites[0]['user']['name']);
        $this->assertTrue($favorites[0]['is_favorite']);

        $search = $this->getJson('/api/connections?search=Plain')->json('data.items');
        $this->assertCount(1, $search);
        $this->assertSame($plain->id, $search[0]['id']);
    }

    public function test_notes_are_private_to_their_owner(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $connection = $this->connect($a, $b);

        $this->actingAsUser($a);
        $this->putJson("/api/connections/{$connection->id}/note", ['note' => 'Met at the summit.'])
            ->assertOk()
            ->assertJsonPath('data.note', 'Met at the summit.');

        $this->getJson("/api/connections/{$connection->id}")
            ->assertOk()
            ->assertJsonPath('data.note', 'Met at the summit.');

        // The other participant sees the connection but not the counterpart's note.
        $this->actingAsUser($b);
        $this->getJson("/api/connections/{$connection->id}")
            ->assertOk()
            ->assertJsonPath('data.note', null);
    }

    public function test_outsiders_cannot_read_or_modify_a_connection(): void
    {
        $connection = $this->connect($this->makeUser(), $this->makeUser());

        $this->actingAsUser();

        $this->getJson("/api/connections/{$connection->id}")->assertStatus(403);
        $this->putJson("/api/connections/{$connection->id}/note", ['note' => 'nope'])->assertStatus(403);
        $this->putJson("/api/connections/{$connection->id}/favorite", ['favorite' => true])->assertStatus(403);
        $this->deleteJson("/api/connections/{$connection->id}")->assertStatus(403);
    }

    public function test_a_participant_can_remove_a_connection(): void
    {
        $a = $this->makeUser();
        $connection = $this->connect($a, $this->makeUser());

        $this->actingAsUser($a);
        $this->deleteJson("/api/connections/{$connection->id}")->assertOk();

        $this->assertDatabaseCount('connections', 0);
    }

    public function test_connected_users_can_see_contact_details_shared_with_connections(): void
    {
        $viewer = $this->makeUser();
        $subject = $this->makeUser(
            ['name' => 'Subject', 'phone' => '+233200000009'],
            [],
            ['phone_visibility' => 'connections', 'email_visibility' => 'connections'],
        );

        $this->actingAsUser($viewer);
        $before = $this->getJson("/api/users/{$subject->id}")->assertOk()->json('data.contact');
        $this->assertSame([], $before);

        $this->connect($viewer, $subject);

        $after = $this->getJson("/api/users/{$subject->id}")->assertOk()->json('data.contact');
        $this->assertSame($subject->email, $after['email']);
        $this->assertSame('+233200000009', $after['phone']);
    }

    public function test_hidden_contact_details_stay_hidden_even_for_connections(): void
    {
        $viewer = $this->makeUser();
        $subject = $this->makeUser(['phone' => '+233200000010']);

        $this->connect($viewer, $subject);
        $this->actingAsUser($viewer);

        $this->assertSame([], $this->getJson("/api/users/{$subject->id}")->assertOk()->json('data.contact'));
    }

    public function test_viewing_a_profile_records_a_profile_view(): void
    {
        $viewer = $this->actingAsUser();
        $subject = $this->makeUser();

        $this->getJson("/api/users/{$subject->id}")->assertOk();

        $this->assertDatabaseHas('profile_views', [
            'viewer_id' => $viewer->id,
            'viewed_user_id' => $subject->id,
        ]);

        // Viewing yourself is not a profile view.
        $this->getJson("/api/users/{$viewer->id}")->assertOk();
        $this->assertDatabaseCount('profile_views', 1);
    }

    private function connect(User $a, User $b, ?int $eventId = null): Connection
    {
        [$one, $two] = Connection::canonicalPair($a->id, $b->id);

        return Connection::create([
            'user_one_id' => $one,
            'user_two_id' => $two,
            'event_id' => $eventId,
            'connected_at' => now(),
        ]);
    }
}
