<?php

namespace App\Services\Auth;

use App\Enums\AuthProvider;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\ProfileService;
use App\Support\SocialIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The find-or-create-or-link logic shared by Google and Apple sign-in, once
 * the token itself has already been verified.
 *
 * Matching is provider-id first, email second: a returning user is found by
 * (auth_provider, provider_id) so a later email change on the provider's
 * side can't orphan the account; a user who originally registered with
 * email/password is found by email and *linked* -- their row's
 * auth_provider/provider_id are set to this social identity. Mingle models a
 * single auth method per account, not multiple linked providers, so from
 * that point on they sign in with the social provider, not the old password.
 */
class SocialAuthService
{
    public function __construct(private readonly ProfileService $profiles) {}

    public function loginOrRegister(
        AuthProvider $provider,
        SocialIdentity $identity,
        ?string $fallbackName = null,
    ): User {
        $user = DB::transaction(function () use ($provider, $identity, $fallbackName) {
            $user = User::query()
                ->where('auth_provider', $provider->value)
                ->where('provider_id', $identity->providerId)
                ->first();

            $user ??= $identity->email
                ? User::query()->where('email', $identity->email)->first()
                : null;

            if ($user) {
                $user->forceFill([
                    'auth_provider' => $provider->value,
                    'provider_id' => $identity->providerId,
                    'is_verified' => $user->is_verified || $identity->emailVerified,
                ])->save();
            } else {
                // `users.email` is required at the schema level; there is no
                // existing account to fall back to, so this can't proceed.
                if (! $identity->email) {
                    throw ValidationException::withMessages([
                        'id_token' => 'This sign-in did not share an email address, which Mingle needs to create an account.',
                    ]);
                }

                $user = User::create([
                    'name' => $identity->name ?? $fallbackName ?? $this->nameFromEmail($identity->email),
                    'email' => $identity->email,
                    'password' => Str::random(40),
                    'auth_provider' => $provider->value,
                    'provider_id' => $identity->providerId,
                    'is_verified' => $identity->emailVerified,
                    // Explicit rather than relying on the column's DB-level
                    // default: a freshly-created model doesn't know that
                    // default until it's reloaded, so the status check right
                    // below would otherwise see `status` as null and treat
                    // every brand-new account as inactive.
                    'status' => UserStatus::Active,
                    'last_active_at' => now(),
                ]);
            }

            $this->profiles->bootstrapFor($user);

            return $user;
        });

        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'id_token' => $user->status === UserStatus::Banned
                    ? 'This account has been banned.'
                    : 'This account is suspended.',
            ]);
        }

        $user->touchLastActive();

        return $user;
    }

    private function nameFromEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return 'Mingle member';
        }

        return ucwords(str_replace(['.', '_', '+'], ' ', strstr($email, '@', true)));
    }
}
