<?php

namespace App\Policies;

use App\Models\ServicioAcuse;
use App\Models\User;
use App\Policies\Concerns\ChecksHelpDeskAccess;

class ServicioAcusePolicy
{
    use ChecksHelpDeskAccess;

    public function view(User $user, ServicioAcuse $acuse): bool
    {
        return $this->permits($user, 'documentos.ver') && $acuse->servicio !== null
            && $this->canReadServicio($user, $acuse->servicio);
    }

    public function download(User $user, ServicioAcuse $acuse): bool
    {
        return $this->permits($user, 'documentos.descargar') && $acuse->servicio !== null
            && $this->canReadServicio($user, $acuse->servicio);
    }
}
