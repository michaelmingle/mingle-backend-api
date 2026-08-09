<?php

namespace App\Services\Push;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mints short-lived OAuth2 access tokens for the FCM HTTP v1 API from a
 * Firebase service account, caching them for just under their real 1-hour
 * lifetime so every push doesn't re-sign a JWT and round-trip Google's token
 * endpoint.
 *
 * Kept as its own class (rather than inlined in FcmPushNotifier) so tests can
 * swap it for a stub that returns a fixed token, instead of needing real
 * Google credentials or network access to exercise the FCM send path.
 */
class GoogleAccessTokenProvider
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const CACHE_KEY = 'fcm:access_token';

    public function token(): ?string
    {
        $credentials = $this->credentials();

        if (! $credentials) {
            return null;
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinutes(50), function () use ($credentials) {
            try {
                $result = $credentials->fetchAuthToken();

                return $result['access_token'] ?? null;
            } catch (Throwable $e) {
                Log::warning('[push:fcm] failed to mint an access token', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    private function credentials(): ?ServiceAccountCredentials
    {
        $json = config('mingle.push.credentials_json');

        if (filled($json)) {
            $decoded = json_decode((string) $json, true);

            return is_array($decoded) ? new ServiceAccountCredentials(self::SCOPE, $decoded) : null;
        }

        $path = config('mingle.push.credentials_path');

        if (filled($path) && is_file($path)) {
            return new ServiceAccountCredentials(self::SCOPE, $path);
        }

        return null;
    }
}
