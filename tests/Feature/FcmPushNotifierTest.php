<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Services\Push\FcmPushNotifier;
use App\Services\Push\GoogleAccessTokenProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmPushNotifierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mingle.push.project_id' => 'mingle-test']);

        // FcmPushNotifier depends on this class, not on the google/auth library
        // directly, precisely so tests can stand in a fixed token here instead
        // of needing a real Firebase service account and network access.
        $this->app->instance(GoogleAccessTokenProvider::class, new class extends GoogleAccessTokenProvider
        {
            public function token(): ?string
            {
                return 'fake-access-token';
            }
        });
    }

    public function test_it_does_nothing_when_the_user_has_no_registered_devices(): void
    {
        $user = $this->makeUser();

        Http::fake();

        $delivered = $this->app->make(FcmPushNotifier::class)->send($user, 'Title', 'Body');

        $this->assertFalse($delivered);
        Http::assertNothingSent();
    }

    public function test_it_posts_to_the_fcm_v1_endpoint_for_every_registered_device(): void
    {
        $user = $this->makeUser();
        $user->deviceTokens()->create(['token' => 'device-1', 'platform' => 'android']);
        $user->deviceTokens()->create(['token' => 'device-2', 'platform' => 'ios']);

        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'projects/mingle-test/messages/1'], 200)]);

        $delivered = $this->app->make(FcmPushNotifier::class)
            ->send($user, 'New connection', 'Ada wants to connect', ['type' => 'connection_request_received']);

        $this->assertTrue($delivered);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://fcm.googleapis.com/v1/projects/mingle-test/messages:send'
                && $request->hasHeader('Authorization', 'Bearer fake-access-token')
                && $request['message']['notification']['title'] === 'New connection'
                && $request['message']['data']['type'] === 'connection_request_received';
        });
    }

    public function test_an_unregistered_token_is_deleted_but_delivery_still_succeeds_for_the_rest(): void
    {
        $user = $this->makeUser();
        $user->deviceTokens()->create(['token' => 'stale-device', 'platform' => 'android']);
        $user->deviceTokens()->create(['token' => 'live-device', 'platform' => 'android']);

        Http::fake([
            'fcm.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['status' => 'UNREGISTERED']], 404)
                ->push(['name' => 'projects/mingle-test/messages/2'], 200),
        ]);

        $delivered = $this->app->make(FcmPushNotifier::class)->send($user, 'Title', 'Body');

        $this->assertTrue($delivered);
        $this->assertSame(1, DeviceToken::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseMissing('device_tokens', ['token' => 'stale-device']);
    }

    public function test_it_returns_false_and_does_not_call_fcm_without_an_access_token(): void
    {
        $this->app->instance(GoogleAccessTokenProvider::class, new class extends GoogleAccessTokenProvider
        {
            public function token(): ?string
            {
                return null;
            }
        });

        $user = $this->makeUser();
        $user->deviceTokens()->create(['token' => 'device-1', 'platform' => 'android']);

        Http::fake();

        $delivered = $this->app->make(FcmPushNotifier::class)->send($user, 'Title', 'Body');

        $this->assertFalse($delivered);
        Http::assertNothingSent();
    }
}
