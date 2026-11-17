<?php

namespace Database\Seeders;

use App\Models\Estado;
use Illuminate\Database\Seeder;

class EstadosHelpDeskSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'REGISTRADO' => 'Registrado',
            'EN_REVISION' => 'En revisión',
            'TURNADO' => 'Turnado',
            'DEVUELTO_AREA' => 'Devuelto al área',
            'EN_ATENCION' => 'En atención',
            'CONCLUIDO' => 'Concluido',
            'CANCELADO' => 'Cancelado',
        ] as $clave => $nombre) {
            Estado::updateOrCreate(['clave' => $clave], ['nombre' => $nombre]);
        }
    }
}
