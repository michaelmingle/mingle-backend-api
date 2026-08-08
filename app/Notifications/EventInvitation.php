<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\User;

class EventInvitation extends MingleNotification
{
    public function __construct(
        public readonly Event $event,
        public readonly ?User $invitedBy = null,
    ) {}

    public function type(): string
    {
        return 'event_invitation';
    }

    public function title(object $notifiable): string
    {
        return 'You have been invited to an event';
    }

    public function body(object $notifiable): string
    {
        $who = $this->invitedBy?->name ?? 'Someone';

        return $who.' invited you to '.$this->event->title.'.';
    }

    public function payload(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'event_slug' => $this->event->slug,
            'starts_at' => $this->event->starts_at?->toIso8601String(),
            'invited_by_id' => $this->invitedBy?->id,
        ];
    }
}
