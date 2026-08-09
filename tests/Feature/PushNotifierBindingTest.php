<?php

namespace Tests\Feature;

use App\Contracts\PushNotifier;
use App\Services\Push\FcmPushNotifier;
use App\Services\Push\NullPushNotifier;
use Tests\TestCase;

/**
 * AppServiceProvider's binding is the actual on/off switch for real push
 * delivery -- these pin down that half-configuring it (enabled with nothing
 * else set, or credentials with the flag left off) always resolves to the
 * no-op, and only the fully-configured case resolves to the real driver.
 */
class PushNotifierBindingTest extends TestCase
{
    public function test_it_resolves_to_the_null_driver_by_default(): void
    {
        $this->assertInstanceOf(NullPushNotifier::class, $this->app->make(PushNotifier::class));
    }

    public function test_enabling_without_credentials_still_resolves_to_the_null_driver(): void
    {
        config(['mingle.push.enabled' => true, 'mingle.push.project_id' => null]);

        $this->assertInstanceOf(NullPushNotifier::class, $this->app->make(PushNotifier::class));
    }

    public function test_credentials_without_enabling_still_resolves_to_the_null_driver(): void
    {
        config([
            'mingle.push.enabled' => false,
            'mingle.push.project_id' => 'mingle-test',
            'mingle.push.credentials_json' => '{"type":"service_account"}',
        ]);

        $this->assertInstanceOf(NullPushNotifier::class, $this->app->make(PushNotifier::class));
    }

    public function test_fully_configured_resolves_to_the_fcm_driver(): void
    {
        config([
            'mingle.push.enabled' => true,
            'mingle.push.project_id' => 'mingle-test',
            'mingle.push.credentials_json' => '{"type":"service_account"}',
        ]);

        $this->assertInstanceOf(FcmPushNotifier::class, $this->app->make(PushNotifier::class));
    }
}
