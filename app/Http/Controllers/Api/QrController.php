<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Qr\ScanQrRequest;
use App\Http\Resources\PublicUserResource;
use App\Models\ConnectionRequest;
use App\Models\QrToken;
use App\Notifications\QrConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrController extends Controller
{
    /** The current user's personal QR payload, created lazily on first request. */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $token = $user->qrTokens()->active()->latest()->first()
            ?? $user->qrTokens()->create([
                'token' => QrToken::generateToken(),
                'is_active' => true,
            ]);

        return $this->ok([
            'token' => $token->token,
            'deep_link' => $token->deepLink(),
        ]);
    }

    /** Rotates the current code, invalidating anything already printed or shared. */
    public function rotate(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->qrTokens()->active()->update(['is_active' => false]);

        $token = $user->qrTokens()->create([
            'token' => QrToken::generateToken(),
            'is_active' => true,
        ]);

        return $this->created([
            'token' => $token->token,
            'deep_link' => $token->deepLink(),
        ], 'QR code rotated.');
    }

    /**
     * Resolves a scanned token to a profile preview. Mirrors GET /api/users/{id}
     * including the subject's contact sharing preferences, but the scanner never
     * needs to know the raw user id.
     */
    public function scan(ScanQrRequest $request): JsonResponse
    {
        $viewer = $request->user();

        $token = QrToken::query()
            ->active()
            ->where('token', $request->string('token')->toString())
            ->with(['user.profile', 'user.contactPreferences', 'user.skills', 'user.interests'])
            ->first();

        if (! $token || ! $token->user) {
            return $this->fail('This QR code is not valid.', 404);
        }

        $subject = $token->user;

        if ($viewer->hasBlockRelationshipWith($subject)) {
            return $this->fail('This QR code is not valid.', 404);
        }

        if ($subject->id !== $viewer->id) {
            $subject->notify(new QrConnection($viewer));
        }

        $sharedNames = $subject->interests
            ->pluck('name', 'id')
            ->intersectByKeys($viewer->interests()->pluck('interests.name', 'interests.id'))
            ->values()
            ->all();

        $resource = (new PublicUserResource($subject))
            ->connected($viewer->isConnectedWith($subject))
            ->sharedInterests($sharedNames)
            ->pendingRequest(
                ConnectionRequest::query()->pendingBetween($viewer->id, $subject->id)->exists()
            );

        return $this->ok($resource->resolve($request));
    }
}
