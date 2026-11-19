<?php

namespace Database\Factories;

use App\Models\Departamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Genera departamentos de prueba con siglas aleatorias.
 * Para los institucionales (RM, SG, ADQ) ejecutar DepartamentosHelpDeskSeeder
 * y consultarlos por siglas, para no duplicar la restricción única.
 *
 * @extends Factory<Departamento>
 */
class DepartamentoFactory extends Factory
{
    protected $model = Departamento::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Departamento '.fake()->unique()->words(2, true),
            'siglas' => 'D'.fake()->unique()->numerify('####'),
            'titular_responsable' => fake()->name(),
            'ubicacion_oficina' => fake()->streetAddress(),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }
}
