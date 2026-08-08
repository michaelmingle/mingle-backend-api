<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    /** Registers a user together with an empty profile and locked-down contact prefs. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'phone' => $request->input('phone'),
                'password' => $request->string('password')->toString(),
                'auth_provider' => $request->input('auth_provider', 'email'),
                'last_active_at' => now(),
            ]);

            $this->profiles->bootstrapFor($user);

            return $user;
        });

        return $this->created([
            'user' => (new UserResource($user->load(['profile', 'contactPreferences', 'skills', 'interests'])))->toArray($request),
            'token' => $this->issueToken($user, $request->input('device_name')),
        ], 'Account created.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            // Same message either way so the endpoint cannot be used to probe
            // which email addresses are registered.
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'email' => $user->status === UserStatus::Banned
                    ? 'This account has been banned.'
                    : 'This account is suspended.',
            ]);
        }

        $user->touchLastActive();

        return $this->ok([
            'user' => (new UserResource($user->load(['profile', 'contactPreferences', 'skills', 'interests'])))->toArray($request),
            'token' => $this->issueToken($user, $request->input('device_name')),
        ], 'Signed in.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(null, 'Signed out.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['profile', 'contactPreferences', 'skills', 'interests']);

        return $this->ok(new UserResource($user));
    }

    private function issueToken(User $user, ?string $deviceName): string
    {
        return $user->createToken($deviceName ?: 'mobile')->plainTextToken;
    }
}
