<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Device\RegisterDeviceRequest;
use App\Http\Requests\Device\UnregisterDeviceRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /**
     * Registers (or re-registers) this installation for push delivery.
     *
     * Tokens are unique per row, not per user: the same token can only ever
     * belong to one account, so if it was previously registered to someone
     * else -- a shared or re-sold device, a fresh login after logout -- this
     * reassigns it rather than leaving a stale duplicate pointed at the old
     * account.
     */
    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        DeviceToken::query()->updateOrCreate(
            ['token' => $request->string('token')->toString()],
            [
                'user_id' => $request->user()->id,
                'platform' => $request->string('platform')->toString(),
                'last_used_at' => now(),
            ],
        );

        return $this->ok(null, 'Device registered.');
    }

    /** Stops push delivery to this installation, e.g. on logout. */
    public function destroy(UnregisterDeviceRequest $request): JsonResponse
    {
        $request->user()->deviceTokens()
            ->where('token', $request->string('token')->toString())
            ->delete();

        return $this->ok(null, 'Device unregistered.');
    }
}
