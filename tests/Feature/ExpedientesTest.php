<?php

namespace Tests\Feature;

use App\Livewire\Expedientes\DetalleExpediente;
use App\Livewire\Expedientes\ListadoExpedientes;
use App\Livewire\Expedientes\RegistrarExpediente;
use App\Models\Area;
use App\Models\Departamento;
use App\Models\Estado;
use App\Models\Operativo;
use App\Models\OperativoPartida;
use App\Models\Partida;
use App\Models\Servicio;
use App\Models\ServicioAcuse;
use App\Models\ServicioArchivo;
use App\Models\ServicioFolio;
use App\Models\TipoServicio;
use App\Models\User;
use Database\Seeders\DepartamentosHelpDeskSeeder;
use Database\Seeders\EstadosHelpDeskSeeder;
use Database\Seeders\PermisosHelpDeskSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpedientesTest extends TestCase
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

    public function test_registro_de_expediente_crea_partida_uno_sin_folio_y_guarda_documentos_privados(): void
    {
        Storage::fake('local');

        $admin = User::factory()->conRol('Administrador')->create();
        $area = Area::factory()->create();
        $deptoRm = Departamento::where('siglas', 'RM')->firstOrFail();
        $tipo = TipoServicio::factory()->create(['requiere_cantidad' => false]);

        $archivoPdf = UploadedFile::fake()->create('oficio-solicitud.pdf', 120, 'application/pdf');
        $acusePdf = UploadedFile::fake()->create('acuse-recibo.pdf', 80, 'application/pdf');

        Livewire::actingAs($admin)
            ->test(RegistrarExpediente::class)
            ->set('form.numero_oficio', 'OF-2026-001')
            ->set('form.fecha_oficio', '2026-11-20')
            ->set('form.fecha_recepcion', '2026-11-20T10:30')
            ->set('form.area_solicitante_id', (string) $area->id)
            ->set('form.nombre_solicitante', 'Mtra. Sofía Herrera')
            ->set('form.dirigido_a', 'Dirección de Recursos Materiales y Servicios Generales')
            ->set('form.asunto', 'Solicitud de reparación de luminarias')
            ->set('form.tipo_servicio_id', (string) $tipo->id)
            ->set('form.departamento_id', (string) $deptoRm->id)
            ->set('archivos', [$archivoPdf])
            ->set('acuse', $acusePdf)
            ->call('guardar')
            ->assertHasNoErrors();

        $servicio = Servicio::where('numero_oficio', 'OF-2026-001')->firstOrFail();
        $this->assertSame(2026, (int) $servicio->ejercicio);
        $this->assertSame($admin->id, $servicio->usuario_id);
        $this->assertSame('REGISTRADO', $servicio->estado->clave);
        $this->assertSame(0, ServicioFolio::count());

        $partida = $servicio->partidas()->sole();
        $this->assertSame(1, $partida->numero_partida);
        $this->assertNull($partida->servicio_folio_id);
        $this->assertSame('REGISTRADO', $partida->estado->clave);
        $this->assertSame('Solicitud de reparación de luminarias', $partida->concepto);

        $archivo = $servicio->archivos()->sole();
        $acuse = $servicio->acuses()->sole();
        Storage::disk('local')->assertExists($archivo->ruta);
        Storage::disk('local')->assertExists($acuse->ruta);

        $this->actingAs($admin)
            ->get(route('expedientes.archivos.descargar', $archivo))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('expedientes.acuses.ver', $acuse))
            ->assertOk();

        $this->assertGreaterThanOrEqual(
            1,
            Activity::where('log_name', 'expedientes')->where('subject_id', $servicio->id)->count()
        );
    }

    public function test_listado_detalle_y_descarga_respetan_alcance_por_departamento_y_asignacion(): void
    {
        Storage::fake('local');

        $deptoRm = Departamento::where('siglas', 'RM')->firstOrFail();
        $deptoSg = Departamento::where('siglas', 'SG')->firstOrFail();
        $estadoRegistrado = Estado::where('clave', 'REGISTRADO')->firstOrFail();

        $jefeRm = User::factory()->enDepartamento($deptoRm)->conRol('Jefe')->create();
        $operativoUser = User::factory()->enDepartamento($deptoRm)->conRol('Operativo')->create();
        $operativo = Operativo::create([
            'nombre' => $operativoUser->name,
            'departamento_id' => $deptoRm->id,
            'user_id' => $operativoUser->id,
            'activo' => true,
        ]);

        // Expediente solo de SG
        $expSg = Servicio::factory()->create(['asunto' => 'Expediente exclusivo SG', 'estado_id' => $estadoRegistrado->id]);
        Partida::factory()->create([
            'servicio_id' => $expSg->id,
            'numero_partida' => 1,
            'departamento_id' => $deptoSg->id,
            'estado_id' => $estadoRegistrado->id,
        ]);
        Storage::disk('local')->put('servicios/2026/sg/doc.pdf', 'contenido-sg');
        $archivoSg = ServicioArchivo::create([
            'servicio_id' => $expSg->id,
            'nombre' => 'doc-sg.pdf',
            'ruta' => 'servicios/2026/sg/doc.pdf',
        ]);

        // Expediente compartido RM + SG
        $expCompartido = Servicio::factory()->create(['asunto' => 'Expediente compartido RM y SG', 'estado_id' => $estadoRegistrado->id]);
        $partidaRm = Partida::factory()->create([
            'servicio_id' => $expCompartido->id,
            'numero_partida' => 1,
            'departamento_id' => $deptoRm->id,
            'concepto' => 'Partida visible para RM',
            'estado_id' => $estadoRegistrado->id,
        ]);
        Partida::factory()->create([
            'servicio_id' => $expCompartido->id,
            'numero_partida' => 2,
            'departamento_id' => $deptoSg->id,
            'concepto' => 'Partida oculta de SG',
            'estado_id' => $estadoRegistrado->id,
        ]);

        // Jefe RM solo ve el compartido y no accede al exclusivo de SG ni a su archivo
        Livewire::actingAs($jefeRm)
            ->test(ListadoExpedientes::class)
            ->assertSee('Expediente compartido RM y SG')
            ->assertDontSee('Expediente exclusivo SG')
            ->assertDontSee('Registrar expediente');

        $this->actingAs($jefeRm)->get(route('expedientes.show', $expSg))->assertForbidden();
        $this->actingAs($jefeRm)->get(route('expedientes.archivos.descargar', $archivoSg))->assertForbidden();
        $this->actingAs($jefeRm)->get(route('expedientes.create'))->assertForbidden();

        // En el detalle del compartido, Jefe RM ve la partida RM y no la de SG
        Livewire::actingAs($jefeRm)
            ->test(DetalleExpediente::class, ['servicio' => $expCompartido])
            ->assertSee('Partida visible para RM')
            ->assertDontSee('Partida oculta de SG');

        // Operativo sin asignación no ve ninguno; con asignación activa ve el compartido
        Livewire::actingAs($operativoUser)
            ->test(ListadoExpedientes::class)
            ->assertDontSee('Expediente compartido RM y SG');

        $asignacion = OperativoPartida::create([
            'partida_id' => $partidaRm->id,
            'operativo_id' => $operativo->id,
            'fecha_asignacion' => now(),
            'activo' => true,
        ]);

        Livewire::actingAs($operativoUser)
            ->test(ListadoExpedientes::class)
            ->assertSee('Expediente compartido RM y SG');

        $asignacion->update(['activo' => false, 'fecha_finalizacion' => now()]);

        Livewire::actingAs($operativoUser)
            ->test(ListadoExpedientes::class)
            ->assertDontSee('Expediente compartido RM y SG');
    }

    public function test_recepcion_en_via_rapida_solo_propone_su_departamento_y_detecta_duplicados(): void
    {
        $deptoRm = Departamento::where('siglas', 'RM')->firstOrFail();
        $deptoSg = Departamento::where('siglas', 'SG')->firstOrFail();
        $recepcionRm = User::factory()->enDepartamento($deptoRm)->conRol('Recepción/Coordinador')->create();
        $area = Area::factory()->create();

        Servicio::factory()->create([
            'numero_oficio' => 'OF-DUP-10',
            'ejercicio' => 2026,
            'area_solicitante_id' => $area->id,
            'asunto' => 'Oficio previo existente',
        ]);

        // Aviso de duplicado y bloqueo de departamento ajeno en vía rápida
        Livewire::actingAs($recepcionRm)
            ->test(RegistrarExpediente::class)
            ->set('form.area_solicitante_id', (string) $area->id)
            ->set('form.fecha_recepcion', '2026-11-20T09:00')
            ->set('form.numero_oficio', 'OF-DUP-10')
            ->assertSee('Aviso de posible duplicado')
            ->set('form.nombre_solicitante', 'Carlos Ruiz')
            ->set('form.dirigido_a', 'RM')
            ->set('form.asunto', 'Solicitud urgente')
            ->set('form.es_via_rapida', true)
            ->set('form.departamento_id', (string) $deptoSg->id)
            ->call('guardar')
            ->assertHasErrors(['form.departamento_id']);

        // Sí permite vía rápida proponiendo su propio departamento
        Livewire::actingAs($recepcionRm)
            ->test(RegistrarExpediente::class)
            ->set('form.area_solicitante_id', (string) $area->id)
            ->set('form.fecha_recepcion', '2026-11-20T09:15')
            ->set('form.numero_oficio', 'OF-RM-VR-1')
            ->set('form.nombre_solicitante', 'Carlos Ruiz')
            ->set('form.dirigido_a', 'RM')
            ->set('form.asunto', 'Solicitud urgente RM')
            ->set('form.es_via_rapida', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $creado = Servicio::where('numero_oficio', 'OF-RM-VR-1')->firstOrFail();
        $this->assertTrue($creado->es_via_rapida);
        $this->assertSame($deptoRm->id, $creado->partidas()->sole()->departamento_id);
    }

    public function test_tipo_servicio_con_cantidad_exige_captura_y_admin_agrega_partidas_y_sustituye_acuse(): void
    {
        Storage::fake('local');

        $admin = User::factory()->conRol('Administrador')->create();
        $area = Area::factory()->create();
        $tipoConCantidad = TipoServicio::factory()->conCantidad()->create();

        // Exige cantidad si tipo_servicio.requiere_cantidad = true
        Livewire::actingAs($admin)
            ->test(RegistrarExpediente::class)
            ->set('form.area_solicitante_id', (string) $area->id)
            ->set('form.fecha_recepcion', '2026-11-20T11:00')
            ->set('form.nombre_solicitante', 'Ana Torres')
            ->set('form.dirigido_a', 'Dirección')
            ->set('form.asunto', 'Suministro de hojas')
            ->set('form.tipo_servicio_id', (string) $tipoConCantidad->id)
            ->call('guardar')
            ->assertHasErrors(['form.cantidad_solicitada']);

        $servicio = Servicio::factory()->create();
        Partida::factory()->create(['servicio_id' => $servicio->id, 'numero_partida' => 1]);

        // Agregar partida #2
        Livewire::actingAs($admin)
            ->test(DetalleExpediente::class, ['servicio' => $servicio])
            ->call('abrirModalPartida')
            ->set('partidaForm.concepto', 'Segunda partida solicitada')
            ->set('partidaForm.tipo_servicio_id', (string) $tipoConCantidad->id)
            ->set('partidaForm.cantidad_solicitada', '15')
            ->set('partidaForm.unidad_medida', 'Cajas')
            ->call('guardarPartida')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('partidas', [
            'servicio_id' => $servicio->id,
            'numero_partida' => 2,
            'concepto' => 'Segunda partida solicitada',
        ]);

        // Registrar y sustituir acuse conservando historial
        $acuse1 = UploadedFile::fake()->create('acuse1.pdf', 50, 'application/pdf');
        $acuse2 = UploadedFile::fake()->create('acuse2.pdf', 60, 'application/pdf');

        Livewire::actingAs($admin)
            ->test(DetalleExpediente::class, ['servicio' => $servicio])
            ->call('abrirModalAcuse')
            ->set('nuevoAcuse', $acuse1)
            ->call('guardarAcuse')
            ->assertHasNoErrors();

        $original = ServicioAcuse::where('servicio_id', $servicio->id)->firstOrFail();

        Livewire::actingAs($admin)
            ->test(DetalleExpediente::class, ['servicio' => $servicio])
            ->call('abrirModalAcuse', $original->id)
            ->set('nuevoAcuse', $acuse2)
            ->set('motivoSustitucion', 'Sello legible en segunda digitalización')
            ->call('guardarAcuse')
            ->assertHasNoErrors();

        $sustituto = ServicioAcuse::where('acuse_original_id', $original->id)->firstOrFail();
        $this->assertSame($admin->id, $sustituto->sustituido_por);
        $this->assertSame('Sello legible en segunda digitalización', $sustituto->motivo_sustitucion);
        $this->assertModelExists($original);
    }
}
