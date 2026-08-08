<?php

namespace App\Notifications;

use App\Contracts\PushNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Base for every in-app notification.
 *
 * Persists to the `notifications` table and, in the same breath, hands the
 * title/body to the PushNotifier seam. The default binding is a no-op stub, so
 * enabling real FCM delivery later is a container binding change and nothing
 * more.
 */
abstract class MingleNotification extends Notification
{
    use Queueable;

    /** Stable machine-readable key surfaced to clients as `data.type`. */
    abstract public function type(): string;

    abstract public function title(object $notifiable): string;

    abstract public function body(object $notifiable): string;

    /** @return array<string, mixed> */
    abstract public function payload(object $notifiable): array;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $payload = array_merge([
            'type' => $this->type(),
            'title' => $this->title($notifiable),
            'body' => $this->body($notifiable),
        ], $this->payload($notifiable));

        $this->push($notifiable, $payload);

        return $payload;
    }

    /** @param  array<string, mixed>  $payload */
    private function push(object $notifiable, array $payload): void
    {
        if (! config('mingle.push.enabled') || ! $notifiable instanceof \App\Models\User) {
            return;
        }

        app(PushNotifier::class)->send(
            $notifiable,
            $payload['title'],
            $payload['body'],
            $payload,
        );
    }
}
