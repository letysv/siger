<?php

namespace Database\Seeders;

use App\Models\Departamento;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartamentosHelpDeskSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ([
                'RM' => 'Recursos Materiales',
                'SG' => 'Servicios Generales',
                'ADQ' => 'Adquisiciones',
            ] as $siglas => $nombre) {
                Departamento::firstOrCreate(
                    ['siglas' => $siglas],
                    ['nombre' => $nombre, 'activo' => true],
                );
            }
        });
    }
}
