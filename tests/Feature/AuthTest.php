<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_user_profile_and_contact_preferences(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'ada@example.com')
            ->assertJsonStructure(['success', 'data' => ['user' => ['id', 'profile'], 'token'], 'message']);

        $user = User::where('email', 'ada@example.com')->firstOrFail();

        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'is_discoverable' => false]);
        $this->assertDatabaseHas('contact_sharing_preferences', [
            'user_id' => $user->id,
            'phone_visibility' => 'hidden',
        ]);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_registration_never_returns_the_password_hash(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $this->assertArrayNotHasKey('password', $response->json('data.user'));
    }

    public function test_registration_validation_errors_use_the_shared_envelope(): void
    {
        $this->postJson('/api/auth/register', ['name' => '', 'email' => 'nope'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors' => ['name', 'email', 'password']]);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertStatus(422)->assertJsonPath('errors.email.0', 'The email has already been taken.');
    }

    public function test_login_returns_a_token(): void
    {
        $user = $this->makeUser(['email' => 'ada@example.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $this->makeUser(['email' => 'ada@example.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_suspended_users_cannot_log_in(): void
    {
        $this->makeUser(['email' => 'suspended@example.com', 'status' => 'suspended']);

        $this->postJson('/api/auth/login', [
            'email' => 'suspended@example.com',
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_me_returns_the_authenticated_user_with_relations(): void
    {
        $user = $this->actingAsUser();

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'profile', 'skills', 'interests']]);
    }

    public function test_protected_routes_reject_guests_with_the_shared_envelope(): void
    {
        $this->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = $this->makeUser(['email' => 'ada@example.com']);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertSame(0, $user->fresh()->tokens()->count());

        // The guard caches the resolved user for the lifetime of the test
        // application, so it has to be reset before re-issuing the request.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    public function test_banned_users_are_blocked_even_with_a_valid_token(): void
    {
        $user = $this->actingAsUser();
        $user->update(['status' => 'banned']);

        $this->getJson('/api/auth/me')->assertStatus(403);
    }
}
