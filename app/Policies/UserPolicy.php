<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** A profile is viewable unless either side has blocked the other. */
    public function view(User $user, User $target): bool
    {
        return $user->id === $target->id || ! $user->hasBlockRelationshipWith($target);
    }

    public function connect(User $user, User $target): bool
    {
        return $user->id !== $target->id && ! $user->hasBlockRelationshipWith($target);
    }

    public function block(User $user, User $target): bool
    {
        return $user->id !== $target->id;
    }

    public function report(User $user, User $target): bool
    {
        return $user->id !== $target->id;
    }

    public function administer(User $user): bool
    {
        return $user->isAdmin();
    }
}
