<?php

namespace Database\Factories;

use App\Models\Estado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Los estados son un catálogo fijo (EstadosHelpDeskSeeder). En las pruebas
 * conviene usar Estado::porClave('REGISTRADO') o ejecutar el seeder; esta
 * factory solo genera estados auxiliares con clave aleatoria.
 *
 * @extends Factory<Estado>
 */
class EstadoFactory extends Factory
{
    protected $model = Estado::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'clave' => 'TEST_'.fake()->unique()->numerify('#####'),
        ];
    }
}
