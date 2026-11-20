<?php

namespace Tests\Feature;

use App\Livewire\Usuarios\GestionUsuarios;
use App\Models\Departamento;
use App\Models\Operativo;
use App\Models\User;
use Database\Seeders\DepartamentosHelpDeskSeeder;
use Database\Seeders\EstadosHelpDeskSeeder;
use Database\Seeders\PermisosHelpDeskSeeder;
use Database\Seeders\PersonalInicialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed([
            DepartamentosHelpDeskSeeder::class,
            EstadosHelpDeskSeeder::class,
            PermisosHelpDeskSeeder::class,
        ]);
    }

    public function test_solo_super_usuario_y_administrador_acceden_a_usuarios(): void
    {
        $deptoRm = Departamento::where('siglas', 'RM')->firstOrFail();

        $admin = User::factory()->conRol('Administrador')->create();
        $super = User::factory()->conRol('Super usuario')->create();
        $jefe = User::factory()->enDepartamento($deptoRm)->conRol('Jefe')->create();
        $recepcion = User::factory()->enDepartamento($deptoRm)->conRol('Recepción/Coordinador')->create();
        $operativo = User::factory()->enDepartamento($deptoRm)->conRol('Operativo')->create();
        $inactivo = User::factory()->conRol('Administrador')->inactivo()->create();

        $this->actingAs($admin)->get(route('usuarios.index'))->assertOk()->assertSee('Gestión de usuarios');
        $this->actingAs($super)->get(route('usuarios.index'))->assertOk();

        $this->actingAs($jefe)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($recepcion)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($operativo)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($inactivo)->get(route('usuarios.index'))->assertRedirect(route('login'));

        Livewire::actingAs($jefe)
            ->test(GestionUsuarios::class)
            ->assertForbidden();
    }

    public function test_administrador_puede_crear_usuario_con_departamento_roles_y_auditoria_sin_contrasena(): void
    {
        $admin = User::factory()->conRol('Administrador')->create();
        $deptoRm = Departamento::where('siglas', 'RM')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(GestionUsuarios::class)
            ->call('abrirCrear')
            ->set('form.name', 'Nazly Jazmín Delfín González')
            ->set('form.username', ' NDelfin ')
            ->set('form.email', ' Nazly.Delfin@Ejemplo.MX ')
            ->set('form.departamento_id', (string) $deptoRm->id)
            ->set('form.roles', ['Jefe'])
            ->set('form.password', 'ClaveSegura123!')
            ->set('form.password_confirmation', 'ClaveSegura123!')
            ->call('guardar')
            ->assertHasNoErrors();

        $creado = User::where('username', 'ndelfin')->firstOrFail();
        $this->assertSame('nazly.delfin@ejemplo.mx', $creado->email);
        $this->assertTrue($creado->activo);
        $this->assertSame($deptoRm->id, $creado->departamento_id);
        $this->assertTrue($creado->hasRole('Jefe'));
        $this->assertTrue(Hash::check('ClaveSegura123!', $creado->password));
        $this->assertNotNull($creado->email_verified_at);

        // No expone password en activity_log
        $logs = Activity::where('log_name', 'usuarios')
            ->where('subject_type', User::class)
            ->where('subject_id', $creado->id)
            ->get();

        $this->assertGreaterThanOrEqual(2, $logs->count());
        foreach ($logs as $log) {
            $json = json_encode($log->properties);
            $this->assertStringNotContainsString('ClaveSegura123!', (string) $json);
            $this->assertStringNotContainsString('password', (string) $json);
        }

        // Usuario y correo únicos
        Livewire::actingAs($admin)
            ->test(GestionUsuarios::class)
            ->call('abrirCrear')
            ->set('form.name', 'Duplicado')
            ->set('form.username', 'ndelfin')
            ->set('form.email', 'nazly.delfin@ejemplo.mx')
            ->set('form.roles', ['Operativo'])
            ->set('form.password', 'ClaveSegura123!')
            ->set('form.password_confirmation', 'ClaveSegura123!')
            ->call('guardar')
            ->assertHasErrors(['form.username', 'form.email']);
    }

    public function test_usuario_sin_correo_se_crea_y_puede_iniciar_sesion_con_username(): void
    {
        $admin = User::factory()->conRol('Administrador')->create();
        $deptoAdq = Departamento::where('siglas', 'ADQ')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(GestionUsuarios::class)
            ->call('abrirCrear')
            ->set('form.name', 'Janeth Martínez López')
            ->set('form.username', 'jmartinezl')
            ->set('form.email', '')
            ->set('form.departamento_id', (string) $deptoAdq->id)
            ->set('form.roles', ['Operativo'])
            ->set('form.password', 'ClaveSegura123!')
            ->set('form.password_confirmation', 'ClaveSegura123!')
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario = User::where('username', 'jmartinezl')->firstOrFail();
        $this->assertNull($usuario->email);

        auth()->logout();

        $this->post('/login', [
            'email' => 'jmartinezl',
            'password' => 'ClaveSegura123!',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_personal_inicial_seeder_carga_54_usuarios_y_38_operativos_sin_correo(): void
    {
        $this->seed(PersonalInicialSeeder::class);
        // Idempotente al ejecutarse dos veces
        $this->seed(PersonalInicialSeeder::class);

        $this->assertSame(54, User::count());
        $this->assertSame(54, User::whereNull('email')->whereNotNull('username')->count());
        $this->assertSame(38, Operativo::whereNotNull('user_id')->count());

        $this->assertDatabaseHas('users', ['username' => 'agonzalez', 'email' => null, 'departamento_id' => null]);
        $this->assertDatabaseHas('users', ['username' => 'jmartinez', 'email' => null]);
        $this->assertDatabaseHas('users', ['username' => 'jmartinezl', 'email' => null]);
        $this->assertDatabaseHas('cat_departamentos', ['siglas' => 'RM', 'titular_responsable' => 'Nazly Jazmín Delfín González']);
        $this->assertDatabaseHas('cat_departamentos', ['siglas' => 'SG', 'titular_responsable' => 'Héctor López Alba']);
        $this->assertDatabaseHas('cat_departamentos', ['siglas' => 'ADQ', 'titular_responsable' => 'Antonino Jarquín Merino']);

        $this->post('/login', [
            'email' => 'agonzalez',
            'password' => 'password',
        ])->assertRedirect('/dashboard');
    }

    public function test_editar_usuario_valida_coherencia_con_operativo_y_autoproteccion(): void
    {
        $admin = User::factory()->conRol('Administrador')->create();
        $deptoSg = Departamento::where('siglas', 'SG')->firstOrFail();
        $deptoAdq = Departamento::where('siglas', 'ADQ')->firstOrFail();

        $operativoUser = User::factory()->enDepartamento($deptoSg)->conRol('Operativo')->create();
        Operativo::create([
            'nombre' => $operativoUser->name,
            'departamento_id' => $deptoSg->id,
            'user_id' => $operativoUser->id,
            'activo' => true,
        ]);

        // No permite cambiar departamento si está vinculado a un operativo en otro departamento
        Livewire::actingAs($admin)
            ->test(GestionUsuarios::class)
            ->call('abrirEditar', $operativoUser->id)
            ->set('form.departamento_id', (string) $deptoAdq->id)
            ->call('guardar')
            ->assertHasErrors(['form.departamento_id']);

        // Auto-protección: admin no puede quitarse su propio rol administrativo ni desactivarse
        Livewire::actingAs($admin)
            ->test(GestionUsuarios::class)
            ->call('abrirEditar', $admin->id)
            ->set('form.roles', ['Jefe'])
            ->call('guardar')
            ->assertHasErrors(['form.roles']);

        Livewire::actingAs($admin)
            ->test(GestionUsuarios::class)
            ->call('abrirEditar', $admin->id)
            ->set('form.activo', false)
            ->call('guardar')
            ->assertHasErrors(['form.activo']);

        Livewire::actingAs($admin)
            ->test(GestionUsuarios::class)
            ->call('alternarEstado', $admin->id)
            ->assertForbidden();
    }

    public function test_desactivar_y_restablecer_contrasena_invalidan_sesiones_activas(): void
    {
        $admin = User::factory()->conRol('Administrador')->create();
        $deptoAdq = Departamento::where('siglas', 'ADQ')->firstOrFail();
        $usuario = User::factory()->enDepartamento($deptoAdq)->conRol('Recepción/Coordinador')->create();

        DB::table('sessions')->insert([
            'id' => 'sesion-prueba-1',
            'user_id' => $usuario->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        // Restablecer contraseña invalida sesión y actualiza hash
        Livewire::actingAs($admin)
            ->test(GestionUsuarios::class)
            ->call('abrirRestablecerContrasena', $usuario->id)
            ->set('passwordForm.password', 'NuevaClave456!')
            ->set('passwordForm.password_confirmation', 'NuevaClave456!')
            ->call('guardarContrasena')
            ->assertHasNoErrors();

        $usuario->refresh();
        $this->assertTrue(Hash::check('NuevaClave456!', $usuario->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-prueba-1']);

        // Desactivar también invalida sesiones
        DB::table('sessions')->insert([
            'id' => 'sesion-prueba-2',
            'user_id' => $usuario->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        Livewire::actingAs($admin)
            ->test(GestionUsuarios::class)
            ->call('alternarEstado', $usuario->id);

        $this->assertFalse($usuario->fresh()->activo);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-prueba-2']);
    }

    public function test_filtros_de_usuarios_funcionan_por_departamento_rol_y_estado(): void
    {
        $admin = User::factory()->conRol('Administrador')->create(['name' => 'Admin Principal']);
        $deptoRm = Departamento::where('siglas', 'RM')->firstOrFail();
        $deptoSg = Departamento::where('siglas', 'SG')->firstOrFail();

        User::factory()->enDepartamento($deptoRm)->conRol('Jefe')->create(['name' => 'Jefe RM Prueba']);
        User::factory()->enDepartamento($deptoSg)->conRol('Operativo')->inactivo()->create(['name' => 'Operativo Inactivo SG']);

        Livewire::actingAs($admin)
            ->test(GestionUsuarios::class)
            ->set('departamentoFiltro', (string) $deptoRm->id)
            ->assertSee('Jefe RM Prueba')
            ->assertDontSee('Operativo Inactivo SG')
            ->set('departamentoFiltro', 'sin_departamento')
            ->assertSee('Admin Principal')
            ->assertDontSee('Jefe RM Prueba')
            ->set('departamentoFiltro', '')
            ->set('estadoFiltro', 'inactivos')
            ->assertSee('Operativo Inactivo SG')
            ->assertDontSee('Jefe RM Prueba');
    }
}
