<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_device_can_be_registered(): void
    {
        $user = $this->actingAsUser();

        $this->postJson('/api/devices', ['token' => 'device-token-1', 'platform' => 'android'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'device-token-1',
            'platform' => 'android',
        ]);
    }

    public function test_registering_platform_must_be_a_known_value(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/devices', ['token' => 'device-token-1', 'platform' => 'windows-phone'])
            ->assertStatus(422);
    }

    public function test_re_registering_the_same_token_reassigns_it_instead_of_duplicating(): void
    {
        $first = $this->actingAsUser();
        $this->postJson('/api/devices', ['token' => 'shared-device', 'platform' => 'ios'])->assertOk();

        $second = $this->actingAsUser();
        $this->postJson('/api/devices', ['token' => 'shared-device', 'platform' => 'ios'])->assertOk();

        $this->assertSame(1, DeviceToken::query()->where('token', 'shared-device')->count());
        $this->assertSame($second->id, DeviceToken::query()->where('token', 'shared-device')->first()->user_id);
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_a_device_can_be_unregistered(): void
    {
        $user = $this->actingAsUser();
        $user->deviceTokens()->create(['token' => 'device-token-1', 'platform' => 'android']);

        $this->postJson('/api/devices/unregister', ['token' => 'device-token-1'])->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'device-token-1']);
    }

    public function test_unregistering_someone_elses_token_does_nothing(): void
    {
        $owner = $this->actingAsUser();
        $owner->deviceTokens()->create(['token' => 'device-token-1', 'platform' => 'android']);

        $this->actingAsUser($this->makeUser());
        $this->postJson('/api/devices/unregister', ['token' => 'device-token-1'])->assertOk();

        $this->assertDatabaseHas('device_tokens', ['token' => 'device-token-1', 'user_id' => $owner->id]);
    }
}
