<?php

namespace App\Policies;

use App\Models\ServicioArchivo;
use App\Models\User;
use App\Policies\Concerns\ChecksHelpDeskAccess;

class ServicioArchivoPolicy
{
    use ChecksHelpDeskAccess;

    public function view(User $user, ServicioArchivo $archivo): bool
    {
        return $this->permits($user, 'documentos.ver') && $archivo->servicio !== null
            && $this->canReadServicio($user, $archivo->servicio);
    }

    public function download(User $user, ServicioArchivo $archivo): bool
    {
        return $this->permits($user, 'documentos.descargar') && $archivo->servicio !== null
            && $this->canReadServicio($user, $archivo->servicio);
    }
}
