<?php

namespace App\Policies;

use App\Models\Departamento;
use App\Models\Partida;
use App\Models\Servicio;
use App\Models\User;
use App\Policies\Concerns\ChecksHelpDeskAccess;

class PartidaPolicy
{
    use ChecksHelpDeskAccess;

    public function view(User $user, Partida $partida): bool
    {
        return $this->permits($user, 'partidas.ver') && $this->canReadPartida($user, $partida);
    }

    public function create(User $user, Servicio $servicio): bool
    {
        return $this->permits($user, 'partidas.crear')
            && $this->generalAccess($user)
            && $servicio->estado()->whereIn('clave', [
                'REGISTRADO', 'EN_REVISION', 'TURNADO', 'EN_ATENCION', 'DEVUELTO_AREA',
            ])->exists();
    }

    public function turnar(User $user, Partida $partida, Departamento $destino): bool
    {
        if (! $this->permits($user, 'partidas.turnar') || ! $destino->activo
            || ! $this->stateIs($partida, ['REGISTRADO', 'EN_REVISION'])
            || $partida->servicio_folio_id !== null) {
            return false;
        }

        return $this->generalAccess($user)
            || ($user->hasRole('Recepción/Coordinador')
                && $this->departmentAccess($user, $partida)
                && (int) $user->departamento_id === (int) $destino->id
                && $partida->servicio()->where('es_via_rapida', true)->exists());
    }

    public function returnar(User $user, Partida $partida, Departamento $destino): bool
    {
        return $this->permits($user, 'partidas.returnar')
            && $this->generalAccess($user)
            && $destino->activo
            && $partida->departamento_id !== null
            && (int) $partida->departamento_id !== (int) $destino->id
            && $partida->servicio_folio_id !== null
            && $this->stateIs($partida, ['TURNADO', 'EN_ATENCION']);
    }

    public function cancelar(User $user, Partida $partida): bool
    {
        if (! $this->permits($user, 'partidas.cancelar')
            || ! ($user->hasRole('Administrador')
                || ($user->hasRole('Jefe') && $this->departmentAccess($user, $partida)))
            || ! $this->stateIs($partida, [
                'REGISTRADO', 'EN_REVISION', 'TURNADO', 'EN_ATENCION', 'DEVUELTO_AREA',
            ])) {
            return false;
        }

        return ! $this->stateIs($partida, ['DEVUELTO_AREA'])
            || $partida->devoluciones()->whereNull('resultado_cierre')->exists();
    }
}
