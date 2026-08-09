<?php

namespace App\Services\Push;

use App\Contracts\PushNotifier;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers to every device the user has registered via FCM's HTTP v1 API.
 *
 * Bound in place of NullPushNotifier only once `mingle.push.enabled` is true
 * and both a project id and credentials resolve -- see AppServiceProvider.
 * A send is best-effort: a device error is logged and, for a token FCM says
 * is no longer valid, the row is removed; it never bubbles up and fails the
 * request that triggered the notification.
 */
class FcmPushNotifier implements PushNotifier
{
    /** FCM error statuses that mean "this registration token will never work again". */
    private const DEAD_TOKEN_STATUSES = ['UNREGISTERED'];

    public function __construct(private readonly GoogleAccessTokenProvider $tokens) {}

    /** @param  array<string, mixed>  $data */
    public function send(User $user, string $title, string $body, array $data = []): bool
    {
        $devices = $user->deviceTokens()->get();

        if ($devices->isEmpty()) {
            return false;
        }

        $accessToken = $this->tokens->token();

        if (! $accessToken) {
            Log::warning('[push:fcm] enabled but no access token could be minted; check FCM_* credentials.');

            return false;
        }

        $projectId = config('mingle.push.project_id');
        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $delivered = false;

        foreach ($devices as $device) {
            try {
                $response = Http::withToken($accessToken)
                    ->timeout(10)
                    ->post($endpoint, [
                        'message' => [
                            'token' => $device->token,
                            'notification' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'data' => $this->stringifyData($data),
                        ],
                    ]);

                if ($response->successful()) {
                    $delivered = true;

                    continue;
                }

                if ($this->tokenIsDead($response)) {
                    $device->delete();
                } else {
                    Log::warning('[push:fcm] delivery failed', [
                        'user_id' => $user->id,
                        'status' => $response->status(),
                        'body' => $response->json() ?? $response->body(),
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('[push:fcm] delivery threw', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        return $delivered;
    }

    /** FCM data payload values must all be strings; null entries are dropped. */
    private function stringifyData(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $out[$key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return $out;
    }

    private function tokenIsDead(Response $response): bool
    {
        $status = $response->json('error.status');

        return $response->status() === 404 || in_array($status, self::DEAD_TOKEN_STATUSES, true);
    }
}
