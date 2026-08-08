<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Transport for mobile push. Real FCM delivery is out of scope for this pass --
 * see App\Services\Push\NullPushNotifier and the README.
 */
interface PushNotifier
{
    /** @param  array<string, mixed>  $data */
    public function send(User $user, string $title, string $body, array $data = []): bool;
}
