<?php

namespace Database\Factories;

use App\Models\Departamento;
use App\Models\Operativo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Operativo>
 */
class OperativoFactory extends Factory
{
    protected $model = Operativo::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'departamento_id' => Departamento::factory(),
            'user_id' => null,
            'activo' => true,
        ];
    }

    /**
     * Vincula el operativo con una cuenta de usuario (cat_operativos.user_id).
     */
    public function conUsuario(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user?->getKey() ?? User::factory(),
        ]);
    }
}
