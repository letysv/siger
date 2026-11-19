<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Departamento;
use App\Models\Estado;
use App\Models\Operativo;
use App\Models\OperativoPartida;
use App\Models\Partida;
use App\Models\Servicio;
use App\Models\SolicitudMaterial;
use App\Models\TipoServicio;
use App\Models\User;
use Database\Seeders\DepartamentosHelpDeskSeeder;
use Database\Seeders\EstadosHelpDeskSeeder;
use Database\Seeders\PermisosHelpDeskSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class Fase0BaseUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertStatus(404);

        $this->post('/register', [
            'name' => 'Usuario Nuevo',
            'email' => 'nuevo@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertStatus(404);
    }

    public function test_inactive_user_is_logged_out_and_redirected_to_login(): void
    {
        $inactiveUser = User::factory()->inactivo()->create();

        $response = $this->actingAs($inactiveUser)->get('/dashboard');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_dashboard_displays_siger_interface_and_role_filtered_menu(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed([
            DepartamentosHelpDeskSeeder::class,
            EstadosHelpDeskSeeder::class,
            PermisosHelpDeskSeeder::class,
        ]);

        $admin = User::factory()->conRol('Administrador')->create();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('SIGER')
            ->assertSee('Expedientes')
            ->assertSee('Partidas')
            ->assertSee('Materiales')
            ->assertSee('Administración')
            ->assertDontSee('Welcome to your Jetstream application!');

        $departamentoSg = Departamento::where('siglas', 'SG')->firstOrFail();
        $operativo = User::factory()
            ->enDepartamento($departamentoSg)
            ->conRol('Operativo')
            ->create();

        $this->actingAs($operativo)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('SIGER')
            ->assertSee('Expedientes')
            ->assertSee('Partidas')
            ->assertDontSee('Materiales')
            ->assertDontSee('Administración');
    }

    public function test_domain_factories_create_valid_records(): void
    {
        $registros = [
            Area::factory()->create(),
            Departamento::factory()->create(),
            Estado::factory()->create(),
            TipoServicio::factory()->create(),
            Operativo::factory()->conUsuario()->create(),
            Servicio::factory()->viaRapida()->create(),
            Partida::factory()->create(),
            OperativoPartida::factory()->create(),
            SolicitudMaterial::factory()->create(),
        ];

        foreach ($registros as $registro) {
            $this->assertModelExists($registro);
        }
    }
}
