<?php

namespace App\Support;

/** What a verified Google/Apple ID token tells us about the signer. */
final readonly class SocialIdentity
{
    public function __construct(
        public string $providerId,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
    ) {}
}
