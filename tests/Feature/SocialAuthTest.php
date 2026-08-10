<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        // A fresh keypair per test, with the JWKS cache cleared, so nothing
        // a previous test signed or cached can leak into this one -- the
        // array cache store is process-lifetime, not per-test.
        Cache::flush();

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($resource, $privateKeyPem);
        $this->privateKeyPem = $privateKeyPem;
        $details = openssl_pkey_get_details($resource);

        $jwks = ['keys' => [[
            'kty' => 'RSA',
            'kid' => 'test-key',
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64url($details['rsa']['n']),
            'e' => $this->base64url($details['rsa']['e']),
        ]]];

        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response($jwks, 200),
            'https://appleid.apple.com/auth/keys' => Http::response($jwks, 200),
        ]);

        config([
            'mingle.social.google_client_ids' => ['google-client-id'],
            'mingle.social.apple_client_ids' => ['app.mingle.mingle'],
        ]);
    }

    // ---------------------------------------------------------------- Google

    public function test_a_new_user_is_created_from_a_google_token(): void
    {
        $response = $this->postJson('/api/auth/google', ['id_token' => $this->googleToken()])
            ->assertCreated()
            ->assertJsonPath('data.user.name', 'Ada Lovelace')
            ->assertJsonPath('data.user.email', 'ada@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'ada@example.com',
            'auth_provider' => 'google',
            'provider_id' => 'google-sub-123',
        ]);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_a_returning_google_user_is_signed_in_not_recreated(): void
    {
        $this->postJson('/api/auth/google', ['id_token' => $this->googleToken()])->assertCreated();

        $this->postJson('/api/auth/google', ['id_token' => $this->googleToken()])
            ->assertOk()
            ->assertJsonPath('message', 'Signed in.');

        $this->assertSame(1, User::query()->where('email', 'ada@example.com')->count());
    }

    public function test_an_existing_email_password_user_is_linked_by_email_on_first_google_sign_in(): void
    {
        $existing = $this->makeUser(['email' => 'ada@example.com', 'auth_provider' => 'email']);

        $this->postJson('/api/auth/google', ['id_token' => $this->googleToken()])
            ->assertOk()
            ->assertJsonPath('data.user.id', $existing->id);

        $existing->refresh();
        $this->assertSame('google', $existing->auth_provider);
        $this->assertSame('google-sub-123', $existing->provider_id);
    }

    public function test_a_token_for_the_wrong_audience_is_rejected(): void
    {
        $this->postJson('/api/auth/google', ['id_token' => $this->googleToken(['aud' => 'someone-elses-app'])])
            ->assertStatus(422);
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $this->postJson('/api/auth/google', ['id_token' => $this->googleToken(['exp' => time() - 60])])
            ->assertStatus(422);
    }

    public function test_a_token_signed_by_a_different_key_is_rejected(): void
    {
        $forgedResource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($forgedResource, $forgedPem);

        $forged = JWT::encode($this->claims([
            'iss' => 'https://accounts.google.com',
            'aud' => 'google-client-id',
            'sub' => 'google-sub-123',
            'email' => 'ada@example.com',
        ]), $forgedPem, 'RS256', 'test-key');

        $this->postJson('/api/auth/google', ['id_token' => $forged])->assertStatus(422);
    }

    public function test_google_sign_in_is_rejected_when_no_client_ids_are_configured(): void
    {
        config(['mingle.social.google_client_ids' => []]);

        $this->postJson('/api/auth/google', ['id_token' => $this->googleToken()])->assertStatus(422);
    }

    public function test_google_sign_in_requires_an_email_to_create_a_new_account(): void
    {
        $this->postJson('/api/auth/google', ['id_token' => $this->googleToken(['email' => null])])
            ->assertStatus(422);
    }

    public function test_a_suspended_account_cannot_sign_in_with_google(): void
    {
        $this->makeUser(['email' => 'ada@example.com', 'status' => 'suspended']);

        $this->postJson('/api/auth/google', ['id_token' => $this->googleToken()])->assertStatus(422);
    }

    // ----------------------------------------------------------------- Apple

    public function test_apple_sign_in_creates_a_user_and_uses_the_client_supplied_name(): void
    {
        $this->postJson('/api/auth/apple', [
            'identity_token' => $this->appleToken(),
            'name' => 'Ada Lovelace',
        ])->assertCreated()
            ->assertJsonPath('data.user.name', 'Ada Lovelace');

        $this->assertDatabaseHas('users', [
            'auth_provider' => 'apple',
            'provider_id' => 'apple-sub-456',
        ]);
    }

    public function test_apple_sign_in_without_a_name_falls_back_to_deriving_one_from_the_email(): void
    {
        $this->postJson('/api/auth/apple', ['identity_token' => $this->appleToken()])
            ->assertCreated()
            ->assertJsonPath('data.user.name', 'Ada');
    }

    public function test_the_name_is_only_applied_on_first_sign_in_not_on_return_visits(): void
    {
        $this->postJson('/api/auth/apple', [
            'identity_token' => $this->appleToken(),
            'name' => 'Ada Lovelace',
        ])->assertCreated();

        $this->postJson('/api/auth/apple', ['identity_token' => $this->appleToken()])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Ada Lovelace');
    }

    public function test_apple_sign_in_is_rejected_when_no_client_ids_are_configured(): void
    {
        config(['mingle.social.apple_client_ids' => []]);

        $this->postJson('/api/auth/apple', ['identity_token' => $this->appleToken()])->assertStatus(422);
    }

    // --------------------------------------------------------------- helpers

    /** @param  array<string, mixed>  $overrides */
    private function claims(array $overrides): array
    {
        return array_filter(array_merge([
            'iat' => time(),
            'exp' => time() + 3600,
        ], $overrides), fn ($value) => $value !== null);
    }

    /** @param  array<string, mixed>  $overrides */
    private function googleToken(array $overrides = []): string
    {
        $claims = $this->claims(array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => 'google-client-id',
            'sub' => 'google-sub-123',
            'email' => 'ada@example.com',
            'email_verified' => true,
            'name' => 'Ada Lovelace',
        ], $overrides));

        return JWT::encode($claims, $this->privateKeyPem, 'RS256', 'test-key');
    }

    /** @param  array<string, mixed>  $overrides */
    private function appleToken(array $overrides = []): string
    {
        $claims = $this->claims(array_merge([
            'iss' => 'https://appleid.apple.com',
            'aud' => 'app.mingle.mingle',
            'sub' => 'apple-sub-456',
            'email' => 'ada@example.com',
            'email_verified' => true,
        ], $overrides));

        return JWT::encode($claims, $this->privateKeyPem, 'RS256', 'test-key');
    }

    private function base64url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
