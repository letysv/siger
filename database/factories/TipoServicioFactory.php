<?php

namespace Database\Factories;

use App\Models\TipoServicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoServicio>
 */
class TipoServicioFactory extends Factory
{
    protected $model = TipoServicio::class;

    public function definition(): array
    {
        return [
            'descripcion' => fake()->unique()->randomElement([
                'Mantenimiento eléctrico', 'Plomería', 'Carpintería', 'Pintura',
                'Cerrajería', 'Limpieza', 'Mudanza de mobiliario', 'Suministro de material',
            ]).' '.fake()->unique()->numerify('###'),
            'requiere_cantidad' => false,
            'activo' => true,
        ];
    }

    public function conCantidad(): static
    {
        return $this->state(fn (array $attributes) => ['requiere_cantidad' => true]);
    }
}
