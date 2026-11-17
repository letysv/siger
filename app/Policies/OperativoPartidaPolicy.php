<?php

namespace App\Policies;

use App\Models\Operativo;
use App\Models\OperativoPartida;
use App\Models\Partida;
use App\Models\User;
use App\Policies\Concerns\ChecksHelpDeskAccess;

class OperativoPartidaPolicy
{
    use ChecksHelpDeskAccess;

    public function create(User $user, Partida $partida, Operativo $operativo): bool
    {
        return $this->permits($user, 'operativos_partida.asignar')
            && $this->canManage($user, $partida)
            && $partida->departamento()->where('activo', true)->exists()
            && $operativo->activo
            && (int) $operativo->departamento_id === (int) $partida->departamento_id
            && ! $partida->asignaciones()->where('operativo_id', $operativo->id)
                ->where('activo', true)->exists();
    }

    public function finalizar(User $user, OperativoPartida $asignacion): bool
    {
        return $this->permits($user, 'operativos_partida.finalizar')
            && $asignacion->activo && $asignacion->fecha_finalizacion === null
            && $asignacion->partida !== null && $this->canManage($user, $asignacion->partida);
    }

    private function canManage(User $user, Partida $partida): bool
    {
        return ($this->generalAccess($user) || $this->departmentAccess($user, $partida))
            && $this->stateIs($partida, ['TURNADO', 'EN_ATENCION']);
    }
}
