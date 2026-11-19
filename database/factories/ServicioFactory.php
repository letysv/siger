<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Estado;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Servicio>
 */
class ServicioFactory extends Factory
{
    protected $model = Servicio::class;

    public function definition(): array
    {
        return [
            'numero_oficio' => 'OF-'.fake()->unique()->numerify('####').'/'.date('Y'),
            'fecha_oficio' => now()->toDateString(),
            'fecha_recepcion' => now(),
            'ejercicio' => (int) date('Y'),
            'area_solicitante_id' => Area::factory(),
            'nombre_solicitante' => fake()->name(),
            'dirigido_a' => 'Dirección General',
            'asunto' => fake()->sentence(6),
            'descripcion' => fake()->paragraph(),
            'es_via_rapida' => false,
            'estado_id' => fn () => Estado::firstOrCreate(['clave' => 'REGISTRADO'], ['nombre' => 'Registrado'])->id,
            'usuario_id' => User::factory(),
        ];
    }

    /**
     * Marca el expediente como canalizado por vía rápida.
     */
    public function viaRapida(): static
    {
        return $this->state(fn (array $attributes) => ['es_via_rapida' => true]);
    }
}
