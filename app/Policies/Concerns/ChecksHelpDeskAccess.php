<?php

namespace App\Policies\Concerns;

use App\Models\Partida;
use App\Models\Servicio;
use App\Models\User;

trait ChecksHelpDeskAccess
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->activo ? null : false;
    }

    protected function permits(User $user, string $permission): bool
    {
        return $user->activo && $user->checkPermissionTo($permission, 'web');
    }

    protected function generalAccess(User $user): bool
    {
        return $user->hasAnyRole(['Super usuario', 'Administrador']);
    }

    protected function centralReceptionAccess(User $user): bool
    {
        return $user->departamento_id === null
            && $user->hasRole('Recepción/Coordinador');
    }

    protected function departmentAccess(User $user, Partida $partida): bool
    {
        return $user->departamento_id !== null
            && $user->hasAnyRole(['Jefe', 'Recepción/Coordinador'])
            && Partida::whereKey($partida->getKey())
                ->where('departamento_id', $user->departamento_id)->exists();
    }

    protected function assignedAccess(User $user, Partida $partida): bool
    {
        return $user->hasRole('Operativo')
            && $partida->asignaciones()->where('activo', true)
                ->whereNull('fecha_finalizacion')
                ->whereHas('operativo', fn ($query) => $query->where('user_id', $user->id))
                ->exists();
    }

    protected function canReadPartida(User $user, Partida $partida): bool
    {
        if ($this->generalAccess($user)
            || $this->departmentAccess($user, $partida)
            || $this->assignedAccess($user, $partida)) {
            return true;
        }

        if ($this->centralReceptionAccess($user)) {
            return Partida::whereKey($partida->getKey())
                ->where(function ($query) use ($user): void {
                    $query->whereNull('departamento_id')
                        ->orWhereHas('servicio', fn ($servicio) => $servicio->where('usuario_id', $user->id));
                })
                ->exists();
        }

        return false;
    }

    protected function canReadServicio(User $user, Servicio $servicio): bool
    {
        if ($this->generalAccess($user)) {
            return true;
        }

        if ($this->centralReceptionAccess($user)) {
            return (int) $servicio->usuario_id === (int) $user->id
                || $servicio->partidas()->whereNull('departamento_id')->exists();
        }

        return $servicio->partidas()->where(function ($query) use ($user): void {
            $query->whereRaw('1 = 0');

            if ($user->departamento_id !== null
                && $user->hasAnyRole(['Jefe', 'Recepción/Coordinador'])) {
                $query->orWhere('departamento_id', $user->departamento_id);
            }

            if ($user->hasRole('Operativo')) {
                $query->orWhereHas('asignaciones', function ($assignments) use ($user): void {
                    $assignments->where('activo', true)->whereNull('fecha_finalizacion')
                        ->whereHas('operativo', fn ($operatives) => $operatives->where('user_id', $user->id));
                });
            }
        })->exists();
    }

    protected function stateIs(Partida $partida, array $states): bool
    {
        return Partida::whereKey($partida->getKey())
            ->whereHas('estado', fn ($query) => $query->whereIn('clave', $states))->exists();
    }
}
