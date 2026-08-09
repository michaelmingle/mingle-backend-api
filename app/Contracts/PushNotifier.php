<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Transport for mobile push. Bound to App\Services\Push\FcmPushNotifier once
 * FCM is enabled and configured, otherwise to the no-op NullPushNotifier --
 * see AppServiceProvider and the README.
 */
interface PushNotifier
{
    /** @param  array<string, mixed>  $data */
    public function send(User $user, string $title, string $body, array $data = []): bool;
}
