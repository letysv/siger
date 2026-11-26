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

    public function update(User $user, Partida $partida): bool
    {
        if (! $this->permits($user, 'partidas.editar')
            || $partida->servicio_folio_id !== null
            || ! $this->stateIs($partida, ['REGISTRADO', 'EN_REVISION'])) {
            return false;
        }

        if ($this->generalAccess($user)) {
            return true;
        }

        if ($this->centralReceptionAccess($user)) {
            return $this->canReadPartida($user, $partida);
        }

        return $user->hasRole('Recepción/Coordinador')
            && $this->departmentAccess($user, $partida)
            && $partida->servicio()->where('es_via_rapida', true)->exists();
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
            && $this->stateIs($partida, ['TURNADO', 'EN_ATENCION', 'EN_REVISION']);
    }

    public function regresarDireccion(User $user, Partida $partida): bool
    {
        return $this->permits($user, 'partidas.regresar_direccion')
            && $user->hasRole('Jefe')
            && $this->departmentAccess($user, $partida)
            && $partida->servicio_folio_id !== null
            && $this->stateIs($partida, ['TURNADO', 'EN_ATENCION']);
    }

    public function iniciarAtencion(User $user, Partida $partida): bool
    {
        return $this->permits($user, 'partidas.iniciar_atencion')
            && ($this->generalAccess($user)
                || $this->departmentAccess($user, $partida)
                || $this->assignedAccess($user, $partida))
            && $partida->departamento_id !== null
            && $partida->servicio_folio_id !== null
            && $this->stateIs($partida, ['TURNADO']);
    }

    public function devolver(User $user, Partida $partida): bool
    {
        return $this->permits($user, 'partidas.devolver')
            && ($this->generalAccess($user) || $this->departmentAccess($user, $partida))
            && $this->stateIs($partida, ['TURNADO', 'EN_ATENCION'])
            && ! $partida->devoluciones()->whereNull('resultado_cierre')->exists();
    }

    public function reanudar(User $user, Partida $partida): bool
    {
        return $this->permits($user, 'partidas.reanudar')
            && ($this->generalAccess($user) || $this->departmentAccess($user, $partida))
            && $this->stateIs($partida, ['DEVUELTO_AREA'])
            && $partida->devoluciones()->whereNull('resultado_cierre')->exists();
    }

    public function concluir(User $user, Partida $partida): bool
    {
        return $this->permits($user, 'partidas.concluir')
            && ($this->generalAccess($user)
                || $this->departmentAccess($user, $partida)
                || $this->assignedAccess($user, $partida))
            && $this->stateIs($partida, ['EN_ATENCION'])
            && ! $partida->registroConclusion()->exists();
    }

    public function cancelar(User $user, Partida $partida): bool
    {
        if (! $this->permits($user, 'partidas.cancelar')
            || ! $this->stateIs($partida, [
                'REGISTRADO', 'EN_REVISION', 'TURNADO', 'EN_ATENCION', 'DEVUELTO_AREA',
            ])) {
            return false;
        }

        $autorizadoPorRol = $user->hasRole('Administrador')
            || ($user->hasRole('Jefe')
                && $this->departmentAccess($user, $partida)
                && (! $this->stateIs($partida, ['REGISTRADO'])
                    || Partida::whereKey($partida->getKey())->whereNotNull('servicio_folio_id')->exists()));

        if (! $autorizadoPorRol) {
            return false;
        }

        return ! $this->stateIs($partida, ['DEVUELTO_AREA'])
            || $partida->devoluciones()->whereNull('resultado_cierre')->exists();
    }
}
