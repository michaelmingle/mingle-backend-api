<?php

namespace App\Services\Push;

use App\Contracts\PushNotifier;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Default binding: records the intent to push and returns. Swap this for an FCM
 * driver once credentials exist -- nothing else in the app needs to change.
 */
class NullPushNotifier implements PushNotifier
{
    /** @param  array<string, mixed>  $data */
    public function send(User $user, string $title, string $body, array $data = []): bool
    {
        Log::debug('[push:stub] notification not delivered (no driver configured)', [
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        return false;
    }
}
