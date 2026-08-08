<?php

namespace App\Notifications;

use App\Models\User;

class ProfileViewed extends MingleNotification
{
    public function __construct(public readonly User $viewer) {}

    public function type(): string
    {
        return 'profile_view';
    }

    public function title(object $notifiable): string
    {
        return 'Someone viewed your profile';
    }

    public function body(object $notifiable): string
    {
        return $this->viewer->name.' viewed your profile.';
    }

    public function payload(object $notifiable): array
    {
        return [
            'viewer_id' => $this->viewer->id,
            'viewer_name' => $this->viewer->name,
        ];
    }
}
