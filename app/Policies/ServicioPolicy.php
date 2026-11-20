<?php

namespace App\Policies;

use App\Models\Servicio;
use App\Models\User;
use App\Policies\Concerns\ChecksHelpDeskAccess;

class ServicioPolicy
{
    use ChecksHelpDeskAccess;

    public function view(User $user, Servicio $servicio): bool
    {
        return $this->permits($user, 'servicios.ver') && $this->canReadServicio($user, $servicio);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, 'servicios.crear')
            && ($this->generalAccess($user) || $user->hasRole('Recepción/Coordinador'));
    }

    public function adjuntarDocumento(User $user, Servicio $servicio): bool
    {
        return $this->create($user) && $this->canReadServicio($user, $servicio);
    }
}
