<?php

namespace Database\Factories;

use App\Models\Departamento;
use App\Models\Estado;
use App\Models\Partida;
use App\Models\Servicio;
use App\Models\TipoServicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partida>
 */
class PartidaFactory extends Factory
{
    protected $model = Partida::class;

    public function definition(): array
    {
        return [
            'servicio_id' => Servicio::factory(),
            'partida_origen_id' => null,
            'numero_partida' => 1,
            'servicio_folio_id' => null,
            'tipo_servicio_id' => TipoServicio::factory(),
            'concepto' => fake()->sentence(8),
            'cantidad_solicitada' => 1,
            'cantidad_atendida' => null,
            'unidad_medida' => 'SERVICIO',
            'departamento_id' => Departamento::factory(),
            'observaciones' => null,
            'nombre_quien_recibe_material' => null,
            'estado_id' => fn () => Estado::firstOrCreate(['clave' => 'REGISTRADO'], ['nombre' => 'Registrado'])->id,
            'fecha_inicio' => null,
            'fecha_conclusion' => null,
            'motivo_cancelacion' => null,
            'cancelado_por' => null,
            'fecha_cancelacion' => null,
        ];
    }
}
