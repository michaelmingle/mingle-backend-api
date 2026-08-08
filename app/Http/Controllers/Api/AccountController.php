<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    /**
     * Soft-deletes the account and revokes every token. Connections and history
     * are retained (soft delete) so the other side of a connection does not see
     * their history vanish; a hard purge is a separate, deliberate operation.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {
            $user->profile()->update(['is_discoverable' => false]);
            $user->qrTokens()->update(['is_active' => false]);
            $user->tokens()->delete();
            $user->delete();
        });

        return $this->ok(null, 'Account deleted.');
    }
}
