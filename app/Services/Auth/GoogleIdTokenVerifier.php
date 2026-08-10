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
 * Verifies a Google Sign-In ID token against Google's published JWKS --
 * signature, issuer, audience, and expiry -- and extracts the claims Mingle
 * cares about. Never trusts a claim without checking the signature first.
 */
class GoogleIdTokenVerifier
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const VALID_ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    public function __construct(private readonly JwksClient $jwks) {}

    public function verify(string $idToken): SocialIdentity
    {
        try {
            $payload = JWT::decode($idToken, $this->jwks->keySet(self::JWKS_URL));
        } catch (ExpiredException|SignatureInvalidException|UnexpectedValueException $e) {
            throw new InvalidSocialTokenException('This Google sign-in token is not valid.', previous: $e);
        } catch (Throwable $e) {
            throw new InvalidSocialTokenException('Could not verify this Google sign-in token.', previous: $e);
        }

        if (! in_array($payload->iss ?? null, self::VALID_ISSUERS, true)) {
            throw new InvalidSocialTokenException('This token was not issued by Google.');
        }

        // Fails closed: an empty GOOGLE_CLIENT_IDS means nothing can ever
        // match, so Google sign-in is simply rejected until it's configured
        // -- never silently accepts a token meant for a different app.
        $allowed = config('mingle.social.google_client_ids');

        if (! in_array($payload->aud ?? null, $allowed, true)) {
            throw new InvalidSocialTokenException('This token was not issued for this app.');
        }

        return new SocialIdentity(
            providerId: (string) $payload->sub,
            email: $payload->email ?? null,
            emailVerified: filter_var($payload->email_verified ?? false, FILTER_VALIDATE_BOOL),
            name: $payload->name ?? null,
        );
    }
}
