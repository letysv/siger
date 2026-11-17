<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Departamento;
use App\Models\Estado;
use App\Models\HistorialSolicitudMaterial;
use App\Models\Operativo;
use App\Models\OperativoPartida;
use App\Models\Partida;
use App\Models\Servicio;
use App\Models\ServicioAcuse;
use App\Models\ServicioArchivo;
use App\Models\ServicioFolio;
use App\Models\SolicitudMaterial;
use App\Models\User;
use Database\Seeders\DepartamentosHelpDeskSeeder;
use Database\Seeders\EstadosHelpDeskSeeder;
use Database\Seeders\PermisosHelpDeskSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HelpDeskPoliciesTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if ($app['config']->get('database.default') !== 'mysql'
            || $app['config']->get('cache.default') !== 'array') {
            $this->markTestSkipped('Ejecutar con tests/phpunit-policies.xml en MySQL y cache array.');
        }

        return $app;
    }

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

    public function test_chief_can_read_shared_expedient_but_only_manage_own_partidas(): void
    {
        $own = $this->partida('RM');
        $other = $this->partida('SG', servicio: $own->servicio);
        $chief = $this->user('Jefe', 'RM');

        $this->assertTrue(Gate::forUser($chief)->allows('view', $own->servicio));
        $this->assertTrue(Gate::forUser($chief)->allows('view', $own));
        $this->assertTrue(Gate::forUser($chief)->allows('cancelar', $own));
        $this->assertFalse(Gate::forUser($chief)->allows('view', $other));
        $this->assertFalse(Gate::forUser($chief)->allows('cancelar', $other));
    }

    public function test_null_department_and_direct_permission_do_not_grant_general_access(): void
    {
        $partida = $this->partida('RM');
        $chief = $this->user('Jefe');
        $operative = $this->user('Operativo', 'RM');
        $operative->givePermissionTo('partidas.cancelar');

        $this->assertFalse(Gate::forUser($chief)->allows('view', $partida->servicio));
        $this->assertFalse(Gate::forUser($operative)->allows('cancelar', $partida));
    }

    public function test_admin_needs_permission_and_inactive_accounts_are_denied(): void
    {
        $partida = $this->partida('RM');
        $admin = $this->user('Administrador');
        $this->assertTrue(Gate::forUser($admin)->allows('view', $partida));
        $admin->removeRole('Administrador');
        $this->assertFalse(Gate::forUser($admin)->allows('view', $partida));
        $admin->assignRole('Administrador');
        $admin->update(['activo' => false]);
        $this->assertFalse(Gate::forUser($admin)->allows('view', $partida));
        $this->assertFalse(Gate::forUser($admin)->allows('create', Servicio::class));
        $this->assertFalse(Gate::forUser($admin)->allows('cancelar', $partida));
    }

    public function test_documents_require_active_assignment_and_their_own_permissions(): void
    {
        $partida = $this->partida('RM', 'DEVUELTO_AREA');
        $user = $this->user('Operativo', 'RM');
        $operative = $this->operative($user);
        $assignment = $this->assignment($partida, $operative);
        $archive = ServicioArchivo::create([
            'servicio_id' => $partida->servicio_id, 'nombre' => Str::uuid().'.pdf', 'ruta' => 'private/test.pdf',
        ]);
        $ack = ServicioAcuse::create([
            'servicio_id' => $partida->servicio_id, 'nombre' => Str::uuid().'.pdf', 'ruta' => 'private/ack.pdf',
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('download', $archive));
        $this->assertTrue(Gate::forUser($user)->allows('view', $ack));
        $this->assertFalse(Gate::forUser($this->user('Operativo', 'RM'))->allows('download', $archive));

        $assignment->update(['activo' => false, 'fecha_finalizacion' => now()]);
        $this->assertFalse(Gate::forUser($user)->allows('download', $archive));
        $this->assertFalse(Gate::forUser($user)->allows('view', $ack));
        $this->assertFalse(Gate::forUser($user)->allows('view', $partida));
    }

    public function test_no_superuser_bypass_for_cancelation_or_terminal_records(): void
    {
        $partida = $this->partida('RM');
        $super = $this->user('Super usuario');
        $super->givePermissionTo('partidas.cancelar');
        $this->assertFalse(Gate::forUser($super)->allows('cancelar', $partida));

        $admin = $this->user('Administrador');
        foreach (['CONCLUIDO', 'CANCELADO'] as $state) {
            $partida->update(['estado_id' => $this->state($state)]);
            $this->assertFalse(Gate::forUser($admin)->allows('cancelar', $partida));
            $this->assertFalse(Gate::forUser($admin)->allows('turnar', [$partida, $this->department('SG')]));
        }
    }

    public function test_department_changes_are_checked_even_for_a_previously_loaded_partida(): void
    {
        $partida = $this->partida('RM');
        $chief = $this->user('Jefe', 'RM');
        $this->assertTrue(Gate::forUser($chief)->allows('view', $partida));
        Partida::whereKey($partida->id)->update(['departamento_id' => $this->department('SG')->id]);
        $this->assertFalse(Gate::forUser($chief)->allows('view', $partida));
        $this->assertFalse(Gate::forUser($chief)->allows('cancelar', $partida));
    }

    public function test_document_permission_is_required_even_with_department_access(): void
    {
        $partida = $this->partida('RM');
        $chief = $this->user('Jefe', 'RM');
        $archive = ServicioArchivo::create([
            'servicio_id' => $partida->servicio_id, 'nombre' => Str::uuid().'.pdf', 'ruta' => 'private/test.pdf',
        ]);
        $chief->roles()->firstOrFail()->revokePermissionTo('documentos.descargar');
        $chief = $chief->fresh();
        $this->assertTrue(Gate::forUser($chief)->allows('view', $archive));
        $this->assertFalse(Gate::forUser($chief)->allows('download', $archive));
    }

    public function test_chief_turns_remain_denied_and_reception_is_limited_to_own_fast_track(): void
    {
        $partida = $this->partida('RM', 'REGISTRADO');
        $chief = $this->user('Jefe', 'RM');
        $chief->givePermissionTo(['partidas.turnar', 'partidas.returnar']);
        $destination = $this->department('RM');
        $this->assertFalse(Gate::forUser($chief)->allows('turnar', [$partida, $destination]));
        $this->assertFalse(Gate::forUser($chief)->allows('returnar', [$partida, $this->department('SG')]));

        $reception = $this->user('Recepción/Coordinador', 'RM');
        $this->assertTrue(Gate::forUser($reception)->allows('create', Servicio::class));
        $this->assertFalse(Gate::forUser($reception)->allows('turnar', [$partida, $destination]));
        $partida->servicio->update(['es_via_rapida' => true]);
        $this->assertTrue(Gate::forUser($reception)->allows('turnar', [$partida, $destination]));
        $this->assertFalse(Gate::forUser($reception)->allows('turnar', [$partida, $this->department('SG')]));
        $partida->update(['departamento_id' => null]);
        $this->assertFalse(Gate::forUser($reception)->allows('view', $partida));
        $this->assertFalse(Gate::forUser($reception)->allows('turnar', [$partida, $destination]));
    }

    public function test_assignments_require_own_department_active_worker_and_no_duplicate(): void
    {
        $partida = $this->partida('RM', 'TURNADO');
        $coordinator = $this->user('Recepción/Coordinador', 'RM');
        $operative = $this->operative($this->user('Operativo', 'RM'));
        $this->assertTrue(Gate::forUser($coordinator)->allows('create', [OperativoPartida::class, $partida, $operative]));
        $this->assertFalse(Gate::forUser($this->user('Jefe', 'SG'))->allows('create', [OperativoPartida::class, $partida, $operative]));
        $operative->update(['activo' => false]);
        $this->assertFalse(Gate::forUser($coordinator)->allows('create', [OperativoPartida::class, $partida, $operative]));
        $operative->update(['activo' => true]);
        $assignment = $this->assignment($partida, $operative);
        $this->assertFalse(Gate::forUser($coordinator)->allows('create', [OperativoPartida::class, $partida, $operative]));
        $this->assertTrue(Gate::forUser($coordinator)->allows('finalizar', $assignment));
        $partida->update(['estado_id' => $this->state('DEVUELTO_AREA')]);
        $this->assertFalse(Gate::forUser($coordinator)->allows('finalizar', $assignment));
    }

    public function test_initial_and_subsequent_turns_check_state_and_destination(): void
    {
        $partida = $this->partida('RM', 'REGISTRADO');
        $admin = $this->user('Administrador');
        $destination = $this->department('SG');
        $this->assertTrue(Gate::forUser($admin)->allows('turnar', [$partida, $destination]));
        $destination->update(['activo' => false]);
        $this->assertFalse(Gate::forUser($admin)->allows('turnar', [$partida, $destination]));
        $destination->update(['activo' => true]);
        $folio = ServicioFolio::create([
            'servicio_id' => $partida->servicio_id, 'departamento_id' => $partida->departamento_id,
            'ejercicio' => now()->year, 'mes' => now()->month,
            'consecutivo' => (ServicioFolio::where('departamento_id', $partida->departamento_id)
                ->where('ejercicio', now()->year)->max('consecutivo') ?? 0) + 1,
            'folio' => 'TEST/'.Str::random(20), 'fecha_asignacion' => now(),
        ]);
        $partida->update(['estado_id' => $this->state('TURNADO'), 'servicio_folio_id' => $folio->id]);
        $this->assertFalse(Gate::forUser($admin)->allows('turnar', [$partida, $destination]));
        $this->assertTrue(Gate::forUser($admin)->allows('returnar', [$partida, $destination]));
        $this->assertFalse(Gate::forUser($admin)->allows('returnar', [$partida, $this->department('RM')]));
        $partida->update(['estado_id' => $this->state('DEVUELTO_AREA')]);
        $this->assertFalse(Gate::forUser($admin)->allows('returnar', [$partida, $destination]));
    }

    public function test_canceling_a_returned_partida_requires_an_open_return(): void
    {
        $partida = $this->partida('RM', 'DEVUELTO_AREA');
        $chief = $this->user('Jefe', 'RM');
        $this->assertFalse(Gate::forUser($chief)->allows('cancelar', $partida));
        $return = $partida->devoluciones()->create([
            'usuario_id' => $chief->id, 'estado_anterior_id' => $this->state('EN_ATENCION'),
            'observaciones' => 'Prueba', 'fecha_devolucion' => now(),
        ]);
        $this->assertTrue(Gate::forUser($chief)->allows('cancelar', $partida));
        $return->update(['resultado_cierre' => 'CANCELADA']);
        $this->assertFalse(Gate::forUser($chief)->allows('cancelar', $partida));
    }

    public function test_material_request_requires_chief_author_and_editable_state(): void
    {
        $partida = $this->partida('RM');
        $chief = $this->user('Jefe', 'RM');
        $request = $this->request($partida, $chief);
        $this->assertTrue(Gate::forUser($chief)->allows('create', [SolicitudMaterial::class, $partida]));
        $this->assertTrue(Gate::forUser($chief)->allows('update', $request));
        $this->assertTrue(Gate::forUser($chief)->allows('enviar', $request));
        $this->assertFalse(Gate::forUser($this->user('Jefe', 'RM'))->allows('update', $request));
        $this->assertFalse(Gate::forUser($this->user('Jefe', 'SG'))->allows('view', $request));
        foreach (['ENVIADA', 'AUTORIZADA', 'RECHAZADA'] as $state) {
            $request->update(['estado' => $state]);
            $this->assertFalse(Gate::forUser($chief)->allows('update', $request));
            $this->assertFalse(Gate::forUser($chief)->allows('enviar', $request));
        }
        $request->update(['estado' => 'INFORMACION_REQUERIDA']);
        $this->assertTrue(Gate::forUser($chief)->allows('update', $request));
    }

    public function test_direction_cannot_self_authorize_or_resolve_terminal_requests(): void
    {
        $partida = $this->partida('RM');
        $chief = $this->user('Jefe', 'RM');
        $request = $this->request($partida, $chief, 'ENVIADA');
        $admin = $this->user('Administrador');
        $this->assertTrue(Gate::forUser($admin)->allows('autorizar', $request));
        $this->assertTrue(Gate::forUser($admin)->allows('rechazar', $request));
        $this->assertFalse(Gate::forUser($chief)->allows('autorizar', $request));
        $chief->assignRole('Administrador');
        $this->assertFalse(Gate::forUser($chief)->allows('autorizar', $request));
        foreach (['BORRADOR', 'INFORMACION_REQUERIDA', 'AUTORIZADA', 'RECHAZADA'] as $state) {
            $request->update(['estado' => $state]);
            $this->assertFalse(Gate::forUser($admin)->allows('autorizar', $request));
        }
    }

    public function test_history_is_read_only_and_does_not_imply_permission_to_delete_records(): void
    {
        $partida = $this->partida('RM');
        $chief = $this->user('Jefe', 'RM');
        $request = $this->request($partida, $chief);
        $history = HistorialSolicitudMaterial::create([
            'solicitud_material_id' => $request->id, 'usuario_id' => $chief->id,
            'accion' => 'CREACION', 'estado_nuevo' => 'BORRADOR', 'fecha' => now(),
        ]);
        $this->assertTrue(Gate::forUser($chief)->allows('view', $history));
        $this->assertFalse(Gate::forUser($this->user('Jefe', 'SG'))->allows('view', $history));
        $super = $this->user('Super usuario');
        foreach ([$partida, $partida->servicio, $request, $history] as $model) {
            $this->assertFalse(Gate::forUser($super)->allows('delete', $model));
            $this->assertFalse(Gate::forUser($super)->allows('forceDelete', $model));
        }
    }

    private function user(string $role, ?string $department = null): User
    {
        $user = User::factory()->create([
            'activo' => true,
            'departamento_id' => $department ? $this->department($department)->id : null,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function department(string $siglas): Departamento
    {
        return Departamento::where('siglas', $siglas)->firstOrFail();
    }

    private function state(string $key): int
    {
        return Estado::where('clave', $key)->firstOrFail()->id;
    }

    private function partida(string $department, string $state = 'EN_ATENCION', ?Servicio $servicio = null): Partida
    {
        if ($servicio === null) {
            $area = Area::create(['clave' => Str::uuid(), 'nombre' => 'Prueba', 'tipo_area' => 'Dirección']);
            $servicio = Servicio::create([
                'fecha_recepcion' => now(), 'ejercicio' => now()->year, 'area_solicitante_id' => $area->id,
                'nombre_solicitante' => 'Prueba', 'dirigido_a' => 'Dirección', 'asunto' => 'Prueba',
                'estado_id' => $this->state('REGISTRADO'), 'usuario_id' => $this->user('Administrador')->id,
            ]);
        }

        return Partida::create([
            'servicio_id' => $servicio->id, 'numero_partida' => $servicio->partidas()->count() + 1,
            'departamento_id' => $this->department($department)->id, 'estado_id' => $this->state($state),
        ]);
    }

    private function operative(User $user): Operativo
    {
        return Operativo::create([
            'nombre' => 'Prueba', 'user_id' => $user->id,
            'departamento_id' => $user->departamento_id, 'activo' => true,
        ]);
    }

    private function assignment(Partida $partida, Operativo $operativo): OperativoPartida
    {
        return OperativoPartida::create([
            'partida_id' => $partida->id, 'operativo_id' => $operativo->id,
            'fecha_asignacion' => now(), 'activo' => true,
        ]);
    }

    private function request(Partida $partida, User $user, string $state = 'BORRADOR'): SolicitudMaterial
    {
        return SolicitudMaterial::create([
            'partida_id' => $partida->id, 'usuario_solicitante_id' => $user->id,
            'descripcion' => 'Prueba', 'justificacion' => 'Prueba', 'estado' => $state,
        ]);
    }
}
