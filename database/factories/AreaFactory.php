<?php

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition(): array
    {
        return [
            'clave' => 'AREA-'.fake()->unique()->numerify('#####'),
            'nombre' => 'Dirección de '.fake()->words(2, true),
            'titular' => fake()->name(),
            'tipo_area' => fake()->randomElement(['Dirección', 'Subdirección', 'Coordinación', 'Unidad']),
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }
}
