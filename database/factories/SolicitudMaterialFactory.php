<?php

namespace Database\Factories;

use App\Models\Partida;
use App\Models\SolicitudMaterial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolicitudMaterial>
 */
class SolicitudMaterialFactory extends Factory
{
    protected $model = SolicitudMaterial::class;

    public function definition(): array
    {
        return [
            'partida_id' => Partida::factory(),
            'usuario_solicitante_id' => User::factory(),
            'descripcion' => fake()->sentence(6),
            'cantidad' => 2,
            'unidad_medida' => 'PIEZA',
            'justificacion' => fake()->sentence(10),
            'estado' => 'BORRADOR',
            'fecha_envio' => null,
            'usuario_resolucion_id' => null,
            'fecha_resolucion' => null,
            'observaciones_direccion' => null,
            'partida_generada_id' => null,
        ];
    }
}
