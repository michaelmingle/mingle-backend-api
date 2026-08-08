<?php

namespace App\Notifications;

use App\Models\ConnectionRequest;

class ConnectionRequestReceived extends MingleNotification
{
    public function __construct(public readonly ConnectionRequest $connectionRequest) {}

    public function type(): string
    {
        return 'connection_request_received';
    }

    public function title(object $notifiable): string
    {
        return 'New connection request';
    }

    public function body(object $notifiable): string
    {
        return $this->connectionRequest->sender?->name.' wants to connect with you.';
    }

    public function payload(object $notifiable): array
    {
        return [
            'connection_request_id' => $this->connectionRequest->id,
            'sender_id' => $this->connectionRequest->sender_id,
            'sender_name' => $this->connectionRequest->sender?->name,
            'event_id' => $this->connectionRequest->event_id,
            'message' => $this->connectionRequest->message,
        ];
    }
}
