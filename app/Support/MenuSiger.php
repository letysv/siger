<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Construye el menú de SIGER filtrado por los permisos y roles del usuario.
 */
class MenuSiger
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function principal(?User $user): array
    {
        return self::filtrar(config('siger.menu', []), $user);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function administracion(?User $user): array
    {
        return self::filtrar(config('siger.menu_administracion', []), $user);
    }

    /**
     * @param  array<int, array<string, mixed>>  $elementos
     * @return list<array<string, mixed>>
     */
    protected static function filtrar(array $elementos, ?User $user): array
    {
        if ($user === null || ! $user->activo) {
            return [];
        }

        $visibles = [];

        foreach ($elementos as $elemento) {
            if (! self::autorizado($elemento, $user)) {
                continue;
            }

            $disponible = Route::has($elemento['ruta']);

            $visibles[] = $elemento + [
                'disponible' => $disponible,
                'url' => $disponible ? route($elemento['ruta']) : null,
                'es_activa' => $disponible && request()->routeIs($elemento['activa'] ?? $elemento['ruta']),
                'estilo' => self::estilo($elemento['color'] ?? 'siger'),
            ];
        }

        return $visibles;
    }

    /**
     * Clases Tailwind completas por color de módulo para tarjetas y menús.
     *
     * @return array{card_hover: string, icon_box: string, title_hover: string, dropdown_active: string}
     */
    public static function estilo(string $color): array
    {
        return match ($color) {
            'emerald' => [
                'card_hover' => 'hover:border-emerald-300',
                'icon_box' => 'bg-emerald-100 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white',
                'title_hover' => 'group-hover:text-emerald-700',
                'dropdown_active' => 'bg-emerald-50 text-emerald-900 font-semibold',
            ],
            'cyan' => [
                'card_hover' => 'hover:border-cyan-300',
                'icon_box' => 'bg-cyan-100 text-cyan-600 group-hover:bg-cyan-600 group-hover:text-white',
                'title_hover' => 'group-hover:text-cyan-700',
                'dropdown_active' => 'bg-cyan-50 text-cyan-900 font-semibold',
            ],
            'amber' => [
                'card_hover' => 'hover:border-amber-300',
                'icon_box' => 'bg-amber-100 text-amber-600 group-hover:bg-amber-600 group-hover:text-white',
                'title_hover' => 'group-hover:text-amber-700',
                'dropdown_active' => 'bg-amber-50 text-amber-900 font-semibold',
            ],
            'indigo' => [
                'card_hover' => 'hover:border-indigo-300',
                'icon_box' => 'bg-indigo-100 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white',
                'title_hover' => 'group-hover:text-indigo-700',
                'dropdown_active' => 'bg-indigo-50 text-indigo-900 font-semibold',
            ],
            'violet' => [
                'card_hover' => 'hover:border-violet-300',
                'icon_box' => 'bg-violet-100 text-violet-600 group-hover:bg-violet-600 group-hover:text-white',
                'title_hover' => 'group-hover:text-violet-700',
                'dropdown_active' => 'bg-violet-50 text-violet-900 font-semibold',
            ],
            'sky' => [
                'card_hover' => 'hover:border-sky-300',
                'icon_box' => 'bg-sky-100 text-sky-600 group-hover:bg-sky-600 group-hover:text-white',
                'title_hover' => 'group-hover:text-sky-700',
                'dropdown_active' => 'bg-sky-50 text-sky-900 font-semibold',
            ],
            default => [
                'card_hover' => 'hover:border-siger-300',
                'icon_box' => 'bg-siger-100 text-siger-700 group-hover:bg-siger-700 group-hover:text-white',
                'title_hover' => 'group-hover:text-siger-700',
                'dropdown_active' => 'bg-siger-50 text-siger-900 font-semibold',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $elemento
     */
    protected static function autorizado(array $elemento, User $user): bool
    {
        $permisos = $elemento['permisos'] ?? [];
        $roles = $elemento['roles'] ?? [];

        if ($permisos === [] && $roles === []) {
            return true;
        }

        if ($roles !== [] && $user->hasAnyRole($roles)) {
            return true;
        }

        foreach ($permisos as $permiso) {
            if ($user->checkPermissionTo($permiso, 'web')) {
                return true;
            }
        }

        return false;
    }
}
