<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksHelpDeskAccess;
use Illuminate\Database\Eloquent\Model;

class CatalogoPolicy
{
    use ChecksHelpDeskAccess;

    public function viewAny(User $user): bool
    {
        return $this->generalAccess($user);
    }

    public function view(User $user, Model $registro): bool
    {
        return $this->generalAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->generalAccess($user);
    }

    public function update(User $user, Model $registro): bool
    {
        return $this->generalAccess($user);
    }

    public function toggleStatus(User $user, Model $registro): bool
    {
        return $this->generalAccess($user);
    }
}
