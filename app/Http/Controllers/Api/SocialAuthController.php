<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuthProvider;
use App\Exceptions\InvalidSocialTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AppleSignInRequest;
use App\Http\Requests\Auth\GoogleSignInRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AppleIdentityTokenVerifier;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Services\Auth\SocialAuthService;
use App\Support\SocialIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SocialAuthController extends Controller
{
    public function __construct(private readonly SocialAuthService $socialAuth) {}

    public function google(GoogleSignInRequest $request, GoogleIdTokenVerifier $verifier): JsonResponse
    {
        $identity = $this->verify($verifier, $request->string('id_token')->toString(), 'id_token');
        $user = $this->socialAuth->loginOrRegister(AuthProvider::Google, $identity);

        return $this->respond($request, $user);
    }

    public function apple(AppleSignInRequest $request, AppleIdentityTokenVerifier $verifier): JsonResponse
    {
        $identity = $this->verify($verifier, $request->string('identity_token')->toString(), 'identity_token');
        $user = $this->socialAuth->loginOrRegister(
            AuthProvider::Apple,
            $identity,
            fallbackName: $request->string('name')->toString() ?: null,
        );

        return $this->respond($request, $user);
    }

    private function verify(
        GoogleIdTokenVerifier|AppleIdentityTokenVerifier $verifier,
        string $token,
        string $field,
    ): SocialIdentity {
        try {
            return $verifier->verify($token);
        } catch (InvalidSocialTokenException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }

    private function respond(FormRequest $request, User $user): JsonResponse
    {
        $wasCreated = $user->wasRecentlyCreated;

        $payload = [
            'user' => (new UserResource($user->load(['profile', 'contactPreferences', 'skills', 'interests'])))->resolve($request),
            'token' => $user->createToken($request->input('device_name') ?: 'mobile')->plainTextToken,
        ];

        return $wasCreated
            ? $this->created($payload, 'Account created.')
            : $this->ok($payload, 'Signed in.');
    }
}
