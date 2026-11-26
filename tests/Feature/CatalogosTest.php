<?php

namespace Tests\Feature;

use App\Livewire\Catalogos\GestionCatalogos;
use App\Models\Area;
use App\Models\Departamento;
use App\Models\Operativo;
use App\Models\TipoServicio;
use App\Models\User;
use Database\Seeders\DepartamentosHelpDeskSeeder;
use Database\Seeders\EstadosHelpDeskSeeder;
use Database\Seeders\PermisosHelpDeskSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CatalogosTest extends TestCase
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

    public function test_solo_super_usuario_y_administrador_acceden_a_catalogos(): void
    {
        $deptoRm = Departamento::where('siglas', 'RM')->firstOrFail();

        $admin = User::factory()->conRol('Administrador')->create();
        $super = User::factory()->conRol('Super usuario')->create();
        $jefe = User::factory()->enDepartamento($deptoRm)->conRol('Jefe')->create();
        $recepcion = User::factory()->enDepartamento($deptoRm)->conRol('Recepción/Coordinador')->create();
        $operativo = User::factory()->enDepartamento($deptoRm)->conRol('Operativo')->create();
        $inactivo = User::factory()->conRol('Administrador')->inactivo()->create();

        $this->actingAs($admin)->get(route('catalogos.index'))->assertOk()->assertSee('Catálogos del sistema');
        $this->actingAs($super)->get(route('catalogos.index'))->assertOk();

        $this->actingAs($jefe)->get(route('catalogos.index'))->assertForbidden();
        $this->actingAs($recepcion)->get(route('catalogos.index'))->assertForbidden();
        $this->actingAs($operativo)->get(route('catalogos.index'))->assertForbidden();
        $this->actingAs($inactivo)->get(route('catalogos.index'))->assertRedirect(route('login'));

        Livewire::actingAs($jefe)
            ->test(GestionCatalogos::class)
            ->assertForbidden();
    }

    public function test_administrador_puede_crear_editar_y_desactivar_area_con_auditoria(): void
    {
        $admin = User::factory()->conRol('Administrador')->create();

        Livewire::actingAs($admin)
            ->test(GestionCatalogos::class)
            ->call('abrirCrear')
            ->set('form.clave', ' dir-jur ')
            ->set('form.nombre', 'Dirección Jurídica')
            ->set('form.titular', 'Lic. Laura Méndez')
            ->set('form.tipo_area', 'Dirección')
            ->call('guardar')
            ->assertHasNoErrors();

        $area = Area::where('clave', 'DIR-JUR')->firstOrFail();
        $this->assertTrue($area->activo);
        $this->assertSame('Dirección Jurídica', $area->nombre);

        // No permite clave duplicada
        Livewire::actingAs($admin)
            ->test(GestionCatalogos::class)
            ->call('abrirCrear')
            ->set('form.clave', 'dir-jur')
            ->set('form.nombre', 'Otra Área')
            ->set('form.tipo_area', 'Dirección')
            ->call('guardar')
            ->assertHasErrors(['form.clave']);

        // Editar y desactivar
        Livewire::actingAs($admin)
            ->test(GestionCatalogos::class)
            ->call('abrirEditar', $area->id)
            ->set('form.nombre', 'Dirección Jurídica y Normativa')
            ->call('guardar')
            ->assertHasNoErrors()
            ->call('alternarEstado', $area->id);

        $area->refresh();
        $this->assertSame('Dirección Jurídica y Normativa', $area->nombre);
        $this->assertFalse($area->activo);

        $this->assertGreaterThanOrEqual(
            3,
            Activity::where('log_name', 'catalogos')
                ->where('subject_type', Area::class)
                ->where('subject_id', $area->id)
                ->count()
        );
    }

    public function test_departamentos_base_protegen_siglas_y_estado_activo(): void
    {
        $admin = User::factory()->conRol('Administrador')->create();
        $deptoRm = Departamento::where('siglas', 'RM')->firstOrFail();

        // Puede actualizar titular y ubicación de un departamento base
        Livewire::actingAs($admin)
            ->test(GestionCatalogos::class, ['tab' => 'departamentos'])
            ->call('abrirEditar', $deptoRm->id)
            ->set('form.titular_responsable', 'Nazly Jazmín Delfín González')
            ->set('form.ubicacion_oficina', 'Planta Baja')
            ->call('guardar')
            ->assertHasNoErrors();

        $deptoRm->refresh();
        $this->assertSame('Nazly Jazmín Delfín González', $deptoRm->titular_responsable);

        // No permite cambiar siglas ni desactivar un departamento base
        Livewire::actingAs($admin)
            ->test(GestionCatalogos::class, ['tab' => 'departamentos'])
            ->call('abrirEditar', $deptoRm->id)
            ->set('form.siglas', 'OTRO')
            ->call('guardar')
            ->assertHasErrors(['form.siglas'])
            ->call('alternarEstado', $deptoRm->id);

        $deptoRm->refresh();
        $this->assertSame('RM', $deptoRm->siglas);
        $this->assertTrue($deptoRm->activo);

        // Sí permite crear y desactivar un departamento nuevo no protegido
        Livewire::actingAs($admin)
            ->test(GestionCatalogos::class, ['tab' => 'departamentos'])
            ->call('abrirCrear')
            ->set('form.siglas', 'mant')
            ->set('form.nombre', 'Mantenimiento Especial')
            ->call('guardar')
            ->assertHasNoErrors();

        $nuevo = Departamento::where('siglas', 'MANT')->firstOrFail();
        Livewire::actingAs($admin)
            ->test(GestionCatalogos::class, ['tab' => 'departamentos'])
            ->call('alternarEstado', $nuevo->id);

        $this->assertFalse($nuevo->fresh()->activo);
    }

    public function test_tipos_de_servicio_se_registran_editan_y_desactivan(): void
    {
        $admin = User::factory()->conRol('Super usuario')->create();

        Livewire::actingAs($admin)
            ->test(GestionCatalogos::class, ['tab' => 'tipos_servicio'])
            ->call('abrirCrear')
            ->set('form.descripcion', 'Fotocopiado institucional')
            ->set('form.requiere_cantidad', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $tipo = TipoServicio::where('descripcion', 'Fotocopiado institucional')->firstOrFail();
        $this->assertTrue($tipo->requiere_cantidad);
        $this->assertTrue($tipo->activo);

        Livewire::actingAs($admin)
            ->test(GestionCatalogos::class, ['tab' => 'tipos_servicio'])
            ->call('alternarEstado', $tipo->id);

        $this->assertFalse($tipo->fresh()->activo);
    }

    public function test_operativos_validan_departamento_y_unicidad_de_usuario_vinculado(): void
    {
        $admin = User::factory()->conRol('Administrador')->create();
        $deptoSg = Departamento::where('siglas', 'SG')->firstOrFail();
        $deptoRm = Departamento::where('siglas', 'RM')->firstOrFail();

        $usuarioSg = User::factory()->enDepartamento($deptoSg)->conRol('Operativo')->create();
        $usuarioRm = User::factory()->enDepartamento($deptoRm)->conRol('Operativo')->create();

        // No permite vincular usuario de otro departamento
        Livewire::actingAs($admin)
            ->test(GestionCatalogos::class, ['tab' => 'operativos'])
            ->call('abrirCrear')
            ->set('form.nombre', 'Nicolás Hernández')
            ->set('form.departamento_id', (string) $deptoSg->id)
            ->set('form.user_id', (string) $usuarioRm->id)
            ->call('guardar')
            ->assertHasErrors(['form.user_id']);

        // Creación válida con usuario del mismo departamento
        Livewire::actingAs($admin)
            ->test(GestionCatalogos::class, ['tab' => 'operativos'])
            ->call('abrirCrear')
            ->set('form.nombre', 'Nicolás Hernández')
            ->set('form.departamento_id', (string) $deptoSg->id)
            ->set('form.user_id', (string) $usuarioSg->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $operativo = Operativo::where('user_id', $usuarioSg->id)->firstOrFail();
        $this->assertSame($deptoSg->id, $operativo->departamento_id);

        // No permite reutilizar el mismo user_id en otro operativo
        Livewire::actingAs($admin)
            ->test(GestionCatalogos::class, ['tab' => 'operativos'])
            ->call('abrirCrear')
            ->set('form.nombre', 'Otro Operativo')
            ->set('form.departamento_id', (string) $deptoSg->id)
            ->set('form.user_id', (string) $usuarioSg->id)
            ->call('guardar')
            ->assertHasErrors(['form.user_id']);

        // Sí permite crear operativo sin cuenta de usuario vinculada
        Livewire::actingAs($admin)
            ->test(GestionCatalogos::class, ['tab' => 'operativos'])
            ->call('abrirCrear')
            ->set('form.nombre', 'Julio Montalvo')
            ->set('form.departamento_id', (string) $deptoSg->id)
            ->set('form.user_id', '')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cat_operativos', [
            'nombre' => 'Julio Montalvo',
            'departamento_id' => $deptoSg->id,
            'user_id' => null,
            'activo' => true,
        ]);
    }
}
