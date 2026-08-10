<?php

namespace App\Services\Auth;

use App\Exceptions\InvalidSocialTokenException;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Fetches and caches a provider's JSON Web Key Set (RFC 7517).
 *
 * Caches the raw JWKS response (a plain array, safe for any cache store) --
 * not the parsed `Key` objects `firebase/php-jwt` builds from it, which are
 * cheap to rebuild on every call and not guaranteed serializable. A 12-hour
 * TTL comfortably covers Google and Apple's own key-rotation cadence without
 * hitting their endpoints on every sign-in.
 */
class JwksClient
{
    /** @return array<string, Key> keyed by `kid`, ready for JWT::decode() */
    public function keySet(string $url): array
    {
        $jwks = Cache::remember(
            'jwks:'.md5($url),
            now()->addHours(12),
            function () use ($url) {
                $response = Http::timeout(10)->get($url);

                if (! $response->successful()) {
                    throw new InvalidSocialTokenException("Could not fetch signing keys from {$url}.");
                }

                return $response->json();
            },
        );

        if (! is_array($jwks) || ! isset($jwks['keys'])) {
            Cache::forget('jwks:'.md5($url));

            throw new InvalidSocialTokenException("Malformed key set from {$url}.");
        }

        return JWK::parseKeySet($jwks);
    }
}
