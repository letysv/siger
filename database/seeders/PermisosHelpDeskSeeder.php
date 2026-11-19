<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermisosHelpDeskSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            'servicios.ver', 'servicios.crear', 'partidas.ver', 'partidas.crear',
            'partidas.turnar', 'partidas.returnar', 'partidas.cancelar',
            'solicitudes_materiales.ver', 'solicitudes_materiales.crear',
            'solicitudes_materiales.editar', 'solicitudes_materiales.enviar',
            'solicitudes_materiales.revisar', 'solicitudes_materiales.solicitar_informacion',
            'solicitudes_materiales.autorizar', 'solicitudes_materiales.rechazar',
            'solicitudes_materiales.ver_historial', 'operativos_partida.asignar',
            'operativos_partida.finalizar', 'documentos.ver', 'documentos.descargar',
        ];

        $consulta = ['servicios.ver', 'partidas.ver', 'documentos.ver', 'documentos.descargar'];
        $asignaciones = ['operativos_partida.asignar', 'operativos_partida.finalizar'];
        $direccion = array_values(array_diff($permisos, [
            'solicitudes_materiales.crear',
            'solicitudes_materiales.editar',
            'solicitudes_materiales.enviar',
        ]));

        // Los alcances G, D, A y R requieren Policies; las celdas P no se conceden.
        $matriz = [
            'Super usuario' => array_values(array_diff($direccion, ['partidas.cancelar'])),
            'Administrador' => $direccion,
            'Jefe' => array_merge($consulta, $asignaciones, [
                'partidas.cancelar',
                'solicitudes_materiales.ver',
                'solicitudes_materiales.crear',
                'solicitudes_materiales.editar',
                'solicitudes_materiales.enviar',
                'solicitudes_materiales.ver_historial',
            ]),
            'Recepción/Coordinador' => array_merge($consulta, $asignaciones, [
                'servicios.crear',
                'partidas.turnar',
            ]),
            'Operativo' => $consulta,
        ];

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        try {
            DB::transaction(function () use ($permisos, $matriz, $registrar): void {
                foreach ($permisos as $permiso) {
                    Permission::findOrCreate($permiso, 'web');
                }

                $registrar->forgetCachedPermissions();

                foreach ($matriz as $nombre => $autorizados) {
                    $rol = Role::findOrCreate($nombre, 'web');
                    $otrosPermisos = $rol->permissions()
                        ->whereNotIn('name', $permisos)
                        ->pluck('name')
                        ->all();

                    $rol->syncPermissions(array_merge($otrosPermisos, $autorizados));
                }
            });
        } finally {
            $registrar->forgetCachedPermissions();
        }
    }
}
