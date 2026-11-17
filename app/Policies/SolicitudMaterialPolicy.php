<?php

namespace App\Policies;

use App\Models\Partida;
use App\Models\SolicitudMaterial;
use App\Models\User;
use App\Policies\Concerns\ChecksHelpDeskAccess;

class SolicitudMaterialPolicy
{
    use ChecksHelpDeskAccess;

    public function view(User $user, SolicitudMaterial $solicitud): bool
    {
        return $this->permits($user, 'solicitudes_materiales.ver')
            && $this->canReadSolicitud($user, $solicitud);
    }

    public function viewHistory(User $user, SolicitudMaterial $solicitud): bool
    {
        return $this->permits($user, 'solicitudes_materiales.ver_historial')
            && $this->canReadSolicitud($user, $solicitud);
    }

    public function create(User $user, Partida $partida): bool
    {
        return $this->permits($user, 'solicitudes_materiales.crear')
            && $this->requestingChief($user, $partida);
    }

    public function update(User $user, SolicitudMaterial $solicitud): bool
    {
        return $this->permits($user, 'solicitudes_materiales.editar')
            && $this->canSubmit($user, $solicitud);
    }

    public function enviar(User $user, SolicitudMaterial $solicitud): bool
    {
        return $this->permits($user, 'solicitudes_materiales.enviar')
            && $this->canSubmit($user, $solicitud);
    }

    public function revisar(User $user, SolicitudMaterial $solicitud): bool
    {
        return $this->permits($user, 'solicitudes_materiales.revisar')
            && $this->generalAccess($user) && $solicitud->estado === 'ENVIADA';
    }

    public function solicitarInformacion(User $user, SolicitudMaterial $solicitud): bool
    {
        return $this->canResolve($user, $solicitud, 'solicitudes_materiales.solicitar_informacion');
    }

    public function autorizar(User $user, SolicitudMaterial $solicitud): bool
    {
        return $this->canResolve($user, $solicitud, 'solicitudes_materiales.autorizar');
    }

    public function rechazar(User $user, SolicitudMaterial $solicitud): bool
    {
        return $this->canResolve($user, $solicitud, 'solicitudes_materiales.rechazar');
    }

    private function canReadSolicitud(User $user, SolicitudMaterial $solicitud): bool
    {
        return ($this->generalAccess($user) && in_array($solicitud->estado, [
            'ENVIADA', 'INFORMACION_REQUERIDA', 'AUTORIZADA', 'RECHAZADA',
        ], true))
            || ($user->hasRole('Jefe') && $solicitud->partidaOriginal !== null
                && $this->departmentAccess($user, $solicitud->partidaOriginal));
    }

    private function requestingChief(User $user, Partida $partida): bool
    {
        return $user->hasRole('Jefe') && $this->departmentAccess($user, $partida)
            && $this->stateIs($partida, ['EN_ATENCION']);
    }

    private function canSubmit(User $user, SolicitudMaterial $solicitud): bool
    {
        return (int) $solicitud->usuario_solicitante_id === (int) $user->id
            && in_array($solicitud->estado, ['BORRADOR', 'INFORMACION_REQUERIDA'], true)
            && $solicitud->partidaOriginal !== null
            && $this->requestingChief($user, $solicitud->partidaOriginal);
    }

    private function canResolve(User $user, SolicitudMaterial $solicitud, string $permission): bool
    {
        return $this->permits($user, $permission) && $this->generalAccess($user)
            && (int) $solicitud->usuario_solicitante_id !== (int) $user->id
            && $solicitud->estado === 'ENVIADA' && $solicitud->partida_generada_id === null
            && $solicitud->partidaOriginal !== null
            && $this->stateIs($solicitud->partidaOriginal, ['EN_ATENCION']);
    }
}
