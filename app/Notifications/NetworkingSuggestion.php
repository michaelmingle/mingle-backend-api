<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\User;

/** "You and X are both at Y and both looking for mentorship." */
class NetworkingSuggestion extends MingleNotification
{
    /** @param  array<int, string>  $reasons */
    public function __construct(
        public readonly User $suggestedUser,
        public readonly ?Event $event = null,
        public readonly array $reasons = [],
    ) {}

    public function type(): string
    {
        return 'networking_suggestion';
    }

    public function title(object $notifiable): string
    {
        return 'Someone worth meeting';
    }

    public function body(object $notifiable): string
    {
        $context = $this->event ? ' at '.$this->event->title : ' nearby';

        return $this->suggestedUser->name.' is'.$context.' and looks like a good match.';
    }

    public function payload(object $notifiable): array
    {
        return [
            'user_id' => $this->suggestedUser->id,
            'user_name' => $this->suggestedUser->name,
            'event_id' => $this->event?->id,
            'reasons' => $this->reasons,
        ];
    }
}
