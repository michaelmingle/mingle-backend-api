<?php

namespace App\Services\Auth;

use App\Exceptions\InvalidSocialTokenException;
use App\Support\SocialIdentity;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Throwable;
use UnexpectedValueException;

/**
 * Verifies a "Sign in with Apple" identity token against Apple's published
 * JWKS. Apple's token never carries the user's name -- that's handed to the
 * client exactly once, on the very first authorization, so the caller passes
 * it through separately (see AppleSignInRequest / SocialAuthService).
 */
class AppleIdentityTokenVerifier
{
    private const JWKS_URL = 'https://appleid.apple.com/auth/keys';

    private const ISSUER = 'https://appleid.apple.com';

    public function __construct(private readonly JwksClient $jwks) {}

    public function verify(string $identityToken): SocialIdentity
    {
        try {
            $payload = JWT::decode($identityToken, $this->jwks->keySet(self::JWKS_URL));
        } catch (ExpiredException|SignatureInvalidException|UnexpectedValueException $e) {
            throw new InvalidSocialTokenException('This Apple sign-in token is not valid.', previous: $e);
        } catch (Throwable $e) {
            throw new InvalidSocialTokenException('Could not verify this Apple sign-in token.', previous: $e);
        }

        if (($payload->iss ?? null) !== self::ISSUER) {
            throw new InvalidSocialTokenException('This token was not issued by Apple.');
        }

        // Fails closed: an empty APPLE_CLIENT_IDS means nothing can ever
        // match, so Apple sign-in is simply rejected until it's configured.
        $allowed = config('mingle.social.apple_client_ids');

        if (! in_array($payload->aud ?? null, $allowed, true)) {
            throw new InvalidSocialTokenException('This token was not issued for this app.');
        }

        return new SocialIdentity(
            providerId: (string) $payload->sub,
            email: $payload->email ?? null,
            emailVerified: filter_var($payload->email_verified ?? false, FILTER_VALIDATE_BOOL),
            name: null,
        );
    }
}
