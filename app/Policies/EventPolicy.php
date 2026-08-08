<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        if ($event->status === EventStatus::Published) {
            return true;
        }

        // Drafts and pending events are only visible to their organizer (or staff).
        return $user->isAdmin() || $user->id === $event->organizer_id;
    }

    public function update(User $user, Event $event): bool
    {
        return $user->isAdmin() || $user->id === $event->organizer_id;
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->isAdmin() || $user->id === $event->organizer_id;
    }

    public function join(User $user, Event $event): bool
    {
        return $event->status === EventStatus::Published;
    }

    public function viewAttendees(User $user, Event $event): bool
    {
        return $this->view($user, $event);
    }
}
