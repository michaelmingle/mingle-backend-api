<?php

namespace App\Notifications;

use App\Models\Event;

class EventReminder extends MingleNotification
{
    public function __construct(public readonly Event $event) {}

    public function type(): string
    {
        return 'event_reminder';
    }

    public function title(object $notifiable): string
    {
        return $this->event->title.' is coming up';
    }

    public function body(object $notifiable): string
    {
        $when = $this->event->starts_at?->diffForHumans() ?? 'soon';

        return 'Turn on networking to see who else is attending. Starts '.$when.'.';
    }

    public function payload(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'starts_at' => $this->event->starts_at?->toIso8601String(),
        ];
    }
}
