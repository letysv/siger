<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DepartamentosHelpDeskSeeder::class,
            EstadosHelpDeskSeeder::class,
            PermisosHelpDeskSeeder::class,
        ]);

        // User::factory(10)->create();

        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
<<<<<<< HEAD
                'name' => 'Leticia Sedas',
=======
                'name' => 'Johan Morales',
                'username' => 'jmorales',
>>>>>>> johan
                'password' => bcrypt('password'),
                'activo' => true,
            ]
        );

        $user->syncRoles(['Super usuario', 'Administrador']);

        $this->call(PersonalInicialSeeder::class);
    }
}
