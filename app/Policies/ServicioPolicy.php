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

    public function update(User $user, Servicio $servicio): bool
    {
        if (! $this->permits($user, 'servicios.crear')
            || $servicio->estado()->whereIn('clave', ['CONCLUIDO', 'CANCELADO'])->exists()) {
            return false;
        }

        if ($this->generalAccess($user)) {
            return true;
        }

        return $user->hasRole('Recepción/Coordinador')
            && $this->canReadServicio($user, $servicio)
            && ! $servicio->folios()->exists();
    }

    public function adjuntarDocumento(User $user, Servicio $servicio): bool
    {
        return $this->create($user) && $this->canReadServicio($user, $servicio);
    }
}
