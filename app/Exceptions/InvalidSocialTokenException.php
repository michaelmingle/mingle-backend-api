<?php

namespace App\Exceptions;

use RuntimeException;

/** A Google/Apple ID token failed signature, issuer, audience, or expiry checks. */
class InvalidSocialTokenException extends RuntimeException {}
