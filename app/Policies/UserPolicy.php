<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksHelpDeskAccess;

class UserPolicy
{
    use ChecksHelpDeskAccess;

    public function viewAny(User $actor): bool
    {
        return $this->generalAccess($actor);
    }

    public function view(User $actor, User $target): bool
    {
        return $this->generalAccess($actor);
    }

    public function create(User $actor): bool
    {
        return $this->generalAccess($actor);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->generalAccess($actor);
    }

    public function toggleStatus(User $actor, User $target): bool
    {
        return $this->generalAccess($actor)
            && (int) $actor->getKey() !== (int) $target->getKey();
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $this->generalAccess($actor);
    }
}
