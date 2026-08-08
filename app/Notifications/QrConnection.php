<?php

namespace App\Notifications;

use App\Models\User;

/** Fired at the QR code's owner when somebody scans their code. */
class QrConnection extends MingleNotification
{
    public function __construct(public readonly User $scannedBy) {}

    public function type(): string
    {
        return 'qr_connection';
    }

    public function title(object $notifiable): string
    {
        return 'Your QR code was scanned';
    }

    public function body(object $notifiable): string
    {
        return $this->scannedBy->name.' scanned your Mingle code.';
    }

    public function payload(object $notifiable): array
    {
        return [
            'user_id' => $this->scannedBy->id,
            'user_name' => $this->scannedBy->name,
        ];
    }
}
