<?php

namespace Database\Factories;

use App\Models\Operativo;
use App\Models\OperativoPartida;
use App\Models\Partida;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperativoPartida>
 */
class OperativoPartidaFactory extends Factory
{
    protected $model = OperativoPartida::class;

    public function definition(): array
    {
        return [
            'partida_id' => Partida::factory(),
            'operativo_id' => Operativo::factory(),
            'fecha_asignacion' => now(),
            'fecha_finalizacion' => null,
            'activo' => true,
        ];
    }

    /**
     * Marca la asignación operativa como finalizada.
     */
    public function finalizada(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
            'fecha_finalizacion' => now(),
        ]);
    }
}
