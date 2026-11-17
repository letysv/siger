<?php

namespace App\Policies;

use App\Models\HistorialSolicitudMaterial;
use App\Models\User;
use App\Policies\Concerns\ChecksHelpDeskAccess;

class HistorialSolicitudMaterialPolicy
{
    use ChecksHelpDeskAccess;

    public function view(User $user, HistorialSolicitudMaterial $historial): bool
    {
        return $historial->solicitud !== null
            && (new SolicitudMaterialPolicy)->viewHistory($user, $historial->solicitud);
    }
}
