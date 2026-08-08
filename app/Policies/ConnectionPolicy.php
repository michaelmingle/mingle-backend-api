<?php

namespace App\Policies;

use App\Models\Connection;
use App\Models\User;

/** Only the two participants may see or act on a connection. */
class ConnectionPolicy
{
    public function view(User $user, Connection $connection): bool
    {
        return $connection->involves($user);
    }

    public function delete(User $user, Connection $connection): bool
    {
        return $connection->involves($user);
    }

    public function favorite(User $user, Connection $connection): bool
    {
        return $connection->involves($user);
    }

    /** Notes are private per participant -- each side reads and writes only their own. */
    public function note(User $user, Connection $connection): bool
    {
        return $connection->involves($user);
    }
}
