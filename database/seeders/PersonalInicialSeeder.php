<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Operativo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Carga inicial de personal operativo, recepción, jefaturas y administración (Subentrega 1C).
 */
class PersonalInicialSeeder extends Seeder
{
    public function run(): void
    {
        $hashTemporal = Hash::make('password');

        DB::transaction(function () use ($hashTemporal): void {
            $departamentos = Departamento::whereIn('siglas', ['RM', 'SG', 'ADQ'])
                ->get()
                ->keyBy('siglas');

            $titulares = [
                'RM' => 'Nazly Jazmín Delfín González',
                'SG' => 'Héctor López Alba',
                'ADQ' => 'Antonino Jarquín Merino',
            ];

            foreach ($titulares as $siglas => $titular) {
                if (isset($departamentos[$siglas])) {
                    $departamentos[$siglas]->update(['titular_responsable' => $titular]);
                }
            }

            foreach ($this->registros() as $registro) {
                $departamentoId = $registro['departamento'] !== null
                    ? $departamentos->get($registro['departamento'])?->id
                    : null;

                $usuario = User::where('username', $registro['username'])->first();

                if ($usuario === null) {
                    $usuario = User::create([
                        'name' => $registro['nombre'],
                        'username' => $registro['username'],
                        'email' => null,
                        'email_verified_at' => now(),
                        'password' => $hashTemporal,
                        'departamento_id' => $departamentoId,
                        'activo' => true,
                    ]);
                } else {
                    $usuario->update([
                        'name' => $registro['nombre'],
                        'departamento_id' => $departamentoId,
                    ]);
                }

                $usuario->syncRoles([$registro['rol']]);

                if ($registro['rol'] === 'Operativo' && $departamentoId !== null) {
                    Operativo::updateOrCreate(
                        ['user_id' => $usuario->id],
                        [
                            'nombre' => $registro['nombre'],
                            'departamento_id' => $departamentoId,
                            'activo' => true,
                        ]
                    );
                }
            }
        });
    }

    /**
     * @return list<array{nombre: string, username: string, rol: string, departamento: ?string}>
     */
    private function registros(): array
    {
        return [
            // Dirección de Recursos Materiales y Servicios Generales
            ['nombre' => 'Ana Patricia González Bautista', 'username' => 'agonzalez', 'rol' => 'Administrador', 'departamento' => null],
            ['nombre' => 'Luis Uriel Valerio Martínez', 'username' => 'lvalerio', 'rol' => 'Recepción/Coordinador', 'departamento' => null],
            ['nombre' => 'Sinaid Rodríguez López', 'username' => 'srodriguez', 'rol' => 'Recepción/Coordinador', 'departamento' => null],
            ['nombre' => 'José Luis Barrera Barradas', 'username' => 'jbarrera', 'rol' => 'Recepción/Coordinador', 'departamento' => null],
            ['nombre' => 'Eduardo Lorenzana Tejeda', 'username' => 'elorenzana', 'rol' => 'Recepción/Coordinador', 'departamento' => null],

            // Departamento de Recursos Materiales (RM)
            ['nombre' => 'Nazly Jazmín Delfín González', 'username' => 'ndelfin', 'rol' => 'Jefe', 'departamento' => 'RM'],
            ['nombre' => 'Azael Fernández Delgado', 'username' => 'afernandez', 'rol' => 'Recepción/Coordinador', 'departamento' => 'RM'],
            ['nombre' => 'Mariana Saraí Pérez Méndez', 'username' => 'mperez', 'rol' => 'Recepción/Coordinador', 'departamento' => 'RM'],
            ['nombre' => 'Elí García Gallardo', 'username' => 'egarcia', 'rol' => 'Recepción/Coordinador', 'departamento' => 'RM'],
            ['nombre' => 'Alejandro Rosas Lorenzo', 'username' => 'arosas', 'rol' => 'Recepción/Coordinador', 'departamento' => 'RM'],
            ['nombre' => 'Jorge Malpica Vera', 'username' => 'jmalpica', 'rol' => 'Operativo', 'departamento' => 'RM'],
            ['nombre' => 'Dulce Irais Campos Guevara', 'username' => 'dcampos', 'rol' => 'Operativo', 'departamento' => 'RM'],
            ['nombre' => 'Alberto Sánchez Ruiz', 'username' => 'asanchez', 'rol' => 'Operativo', 'departamento' => 'RM'],
            ['nombre' => 'José Manuel Rodríguez Hernández', 'username' => 'jrodriguez', 'rol' => 'Operativo', 'departamento' => 'RM'],
            ['nombre' => 'Filiberto Ruiz Vargas', 'username' => 'fruiz', 'rol' => 'Operativo', 'departamento' => 'RM'],
            ['nombre' => 'Michelle Cuallo Nieda', 'username' => 'mcuallo', 'rol' => 'Operativo', 'departamento' => 'RM'],
            ['nombre' => 'Lucila Colorado Ortíz', 'username' => 'lcolorado', 'rol' => 'Operativo', 'departamento' => 'RM'],
            ['nombre' => 'Oscar Hernández Hernández', 'username' => 'ohernandez', 'rol' => 'Operativo', 'departamento' => 'RM'],

            // Departamento de Servicios Generales (SG)
            ['nombre' => 'Héctor López Alba', 'username' => 'hlopez', 'rol' => 'Jefe', 'departamento' => 'SG'],
            ['nombre' => 'Martha Valdés', 'username' => 'mvaldes', 'rol' => 'Recepción/Coordinador', 'departamento' => 'SG'],
            ['nombre' => 'Carolina Hernández', 'username' => 'chernandez', 'rol' => 'Recepción/Coordinador', 'departamento' => 'SG'],
            ['nombre' => 'Reynaldo Gómez', 'username' => 'rgomez', 'rol' => 'Recepción/Coordinador', 'departamento' => 'SG'],
            ['nombre' => 'Dulce Arenas', 'username' => 'darenas', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Ilse Herrera', 'username' => 'iherrera', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Nicolás Hernández', 'username' => 'nhernandez', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Rafael Suárez', 'username' => 'rsuarez', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Jaime Libreros', 'username' => 'jlibreros', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Marco Antonio Guzmán', 'username' => 'mguzman', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Pedro Hernández', 'username' => 'phernandez', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Víctor Rosas', 'username' => 'vrosas', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Felipe Rivas', 'username' => 'frivas', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Jesús Contreras', 'username' => 'jcontreras', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Galdino Jiménez', 'username' => 'gjimenez', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Jaime García', 'username' => 'jgarcia', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Juan Carlos Cervantes', 'username' => 'jcervantes', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'José Juan Viveros', 'username' => 'jviveros', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Naim Arellano', 'username' => 'narellano', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Javier Martínez', 'username' => 'jmartinez', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Ricardo Sedeño', 'username' => 'rsedeno', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Fernando Díaz', 'username' => 'fdiaz', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Fernando Huesca', 'username' => 'fhuesca', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Ángel Hernández', 'username' => 'ahernandez', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Julio Montalvo', 'username' => 'jmontalvo', 'rol' => 'Operativo', 'departamento' => 'SG'],
            ['nombre' => 'Francisco Quiroz', 'username' => 'fquiroz', 'rol' => 'Operativo', 'departamento' => 'SG'],

            // Departamento de Adquisiciones (ADQ)
            ['nombre' => 'Antonino Jarquín Merino', 'username' => 'ajarquin', 'rol' => 'Jefe', 'departamento' => 'ADQ'],
            ['nombre' => 'Ana Patricia Figueroa Blanco', 'username' => 'afigueroa', 'rol' => 'Recepción/Coordinador', 'departamento' => 'ADQ'],
            ['nombre' => 'Félix Morales Cortés', 'username' => 'fmorales', 'rol' => 'Operativo', 'departamento' => 'ADQ'],
            ['nombre' => 'Guillermo Iván Hernández Domínguez', 'username' => 'ghernandez', 'rol' => 'Operativo', 'departamento' => 'ADQ'],
            ['nombre' => 'Grisel Viridiana García Ortigoza', 'username' => 'ggarcia', 'rol' => 'Operativo', 'departamento' => 'ADQ'],
            ['nombre' => 'Janeth Martínez López', 'username' => 'jmartinezl', 'rol' => 'Operativo', 'departamento' => 'ADQ'],
            ['nombre' => 'José Antonio Filobello Viveros', 'username' => 'jfilobello', 'rol' => 'Operativo', 'departamento' => 'ADQ'],
            ['nombre' => 'Emiliano Alfredo Raya López', 'username' => 'eraya', 'rol' => 'Operativo', 'departamento' => 'ADQ'],
            ['nombre' => 'Raquel Valdelamar Quiroz', 'username' => 'rvaldelamar', 'rol' => 'Operativo', 'departamento' => 'ADQ'],
            ['nombre' => 'Cruz Leopoldo Castillo Grajales', 'username' => 'ccastillo', 'rol' => 'Operativo', 'departamento' => 'ADQ'],
        ];
    }
}
