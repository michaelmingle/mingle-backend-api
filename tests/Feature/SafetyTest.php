<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocking_prevents_a_connection_request_in_both_directions(): void
    {
        $blocker = $this->makeUser();
        $blocked = $this->makeUser();

        $this->actingAsUser($blocker);
        $this->postJson("/api/users/{$blocked->id}/block")->assertOk();

        $this->assertDatabaseHas('blocked_users', [
            'user_id' => $blocker->id,
            'blocked_user_id' => $blocked->id,
        ]);

        // The blocker cannot reach out...
        $this->postJson("/api/users/{$blocked->id}/connect")->assertStatus(403);

        // ...and neither can the blocked party.
        $this->actingAsUser($blocked);
        $this->postJson("/api/users/{$blocker->id}/connect")->assertStatus(403);

        $this->assertDatabaseCount('connection_requests', 0);
    }

    public function test_blocking_hides_the_profile_from_both_sides(): void
    {
        $blocker = $this->makeUser();
        $blocked = $this->makeUser();

        $this->actingAsUser($blocker);
        $this->postJson("/api/users/{$blocked->id}/block")->assertOk();

        $this->getJson("/api/users/{$blocked->id}")->assertStatus(403);

        $this->actingAsUser($blocked);
        $this->getJson("/api/users/{$blocker->id}")->assertStatus(403);
    }

    public function test_unblocking_restores_access(): void
    {
        $blocker = $this->actingAsUser();
        $blocked = $this->makeUser();

        $this->postJson("/api/users/{$blocked->id}/block")->assertOk();
        $this->deleteJson("/api/users/{$blocked->id}/block")->assertOk();

        $this->assertDatabaseCount('blocked_users', 0);
        $this->getJson("/api/users/{$blocked->id}")->assertOk();
        $this->postJson("/api/users/{$blocked->id}/connect")->assertCreated();
    }

    public function test_a_user_cannot_block_or_report_themselves(): void
    {
        $user = $this->actingAsUser();

        $this->postJson("/api/users/{$user->id}/block")->assertStatus(403);
        $this->postJson("/api/users/{$user->id}/report", ['reason' => 'spam'])->assertStatus(403);
    }

    public function test_a_user_can_report_another_user(): void
    {
        $reporter = $this->actingAsUser();
        $target = $this->makeUser();

        $this->postJson("/api/users/{$target->id}/report", [
            'reason' => 'harassment',
            'details' => 'Repeated unwanted messages.',
        ])->assertCreated();

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'reported_user_id' => $target->id,
            'reason' => 'harassment',
            'status' => 'pending',
        ]);
    }

    public function test_reporting_requires_a_reason(): void
    {
        $this->actingAsUser();
        $target = $this->makeUser();

        $this->postJson("/api/users/{$target->id}/report", [])->assertStatus(422);
    }

    public function test_deleting_an_account_soft_deletes_it_and_revokes_tokens(): void
    {
        $user = $this->makeUser(['email' => 'leaving@example.com'], ['is_discoverable' => true]);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'leaving@example.com',
            'password' => 'password',
        ])->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/account')
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertFalse($user->profile()->first()->is_discoverable);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }
}
