<?php

namespace App\Notifications;

use App\Models\Connection;
use App\Models\User;

class ConnectionRequestAccepted extends MingleNotification
{
    /**
     * Named `userConnection` rather than `connection`: Notification's Queueable
     * trait already owns a `$connection` property (the queue connection).
     */
    public function __construct(
        public readonly Connection $userConnection,
        public readonly User $acceptedBy,
    ) {}

    public function type(): string
    {
        return 'connection_request_accepted';
    }

    public function title(object $notifiable): string
    {
        return 'Connection accepted';
    }

    public function body(object $notifiable): string
    {
        return $this->acceptedBy->name.' accepted your connection request.';
    }

    public function payload(object $notifiable): array
    {
        return [
            'connection_id' => $this->userConnection->id,
            'user_id' => $this->acceptedBy->id,
            'user_name' => $this->acceptedBy->name,
            'event_id' => $this->userConnection->event_id,
        ];
    }
}
