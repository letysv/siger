<?php

namespace App\Livewire\Materiales;

use App\Models\Departamento;
use App\Models\Estado;
use App\Models\Partida;
use App\Models\SolicitudMaterial;
use App\Models\TipoServicio;
use App\Models\User;
use App\Services\SolicitudMaterialService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class GestionSolicitudesMateriales extends Component
{
    use WithPagination;

    public string $buscar = '';

    public string $estadoFiltro = '';

    public string $departamentoFiltro = '';

    public bool $modalFormularioAbierto = false;

    public bool $modalEnviarAbierto = false;

    public bool $modalSolicitarInfoAbierto = false;

    public bool $modalRechazarAbierto = false;

    public bool $modalAutorizarAbierto = false;

    public bool $modalDetalleAbierto = false;

    public ?int $solicitudEditandoId = null;

    public ?int $solicitudSeleccionadaId = null;

    public string $partidaOriginalId = '';

    /** @var array<string, mixed> */
    public array $form = [
        'descripcion' => '',
        'cantidad' => '',
        'unidad_medida' => '',
        'justificacion' => '',
    ];

    public string $respuestaAclaracion = '';

    public string $observacionesDireccion = '';

    public string $motivoRechazo = '';

    /** @var array<string, mixed> */
    public array $autorizacionForm = [
        'tipo_servicio_id' => '',
        'concepto' => '',
        'cantidad_solicitada' => '',
        'unidad_medida' => '',
        'observaciones_direccion' => '',
    ];

    public ?string $mensajeExito = null;

    public ?string $mensajeError = null;

    public function mount(): void
    {
        $usuario = $this->autorizarModulo();

        $partidaQueryId = request()->integer('partida');
        if ($partidaQueryId > 0 && $usuario->checkPermissionTo('solicitudes_materiales.crear', 'web')) {
            $partida = Partida::find($partidaQueryId);
            if ($partida !== null && Gate::allows('create', [SolicitudMaterial::class, $partida])) {
                $this->abrirCrear($partida->id);
            }
        }
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedEstadoFiltro(): void
    {
        $this->resetPage();
    }

    public function updatedDepartamentoFiltro(): void
    {
        $this->resetPage();
    }

    public function abrirCrear(?int $partidaId = null): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $usuario = $this->autorizarModulo();
        abort_unless(
            $usuario->checkPermissionTo('solicitudes_materiales.crear', 'web')
            && $usuario->hasRole('Jefe')
            && $usuario->departamento_id !== null,
            403
        );

        $this->solicitudEditandoId = null;
        $this->solicitudSeleccionadaId = null;
        $this->partidaOriginalId = $partidaId !== null ? (string) $partidaId : '';
        $this->form = [
            'descripcion' => '',
            'cantidad' => '',
            'unidad_medida' => '',
            'justificacion' => '',
        ];
        $this->modalFormularioAbierto = true;
    }

    public function abrirEditar(int $solicitudId): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $solicitud = SolicitudMaterial::with('partidaOriginal')->findOrFail($solicitudId);
        Gate::authorize('update', $solicitud);

        $this->solicitudEditandoId = $solicitud->id;
        $this->solicitudSeleccionadaId = $solicitud->id;
        $this->partidaOriginalId = (string) $solicitud->partida_id;
        $this->form = [
            'descripcion' => (string) $solicitud->descripcion,
            'cantidad' => $solicitud->cantidad !== null ? (string) $solicitud->cantidad : '',
            'unidad_medida' => (string) ($solicitud->unidad_medida ?? ''),
            'justificacion' => (string) $solicitud->justificacion,
        ];
        $this->modalFormularioAbierto = true;
    }

    public function cerrarModalFormulario(): void
    {
        $this->modalFormularioAbierto = false;
        $this->solicitudEditandoId = null;
        $this->solicitudSeleccionadaId = null;
        $this->partidaOriginalId = '';
        $this->resetValidation();
    }

    public function guardarSolicitud(SolicitudMaterialService $service): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            if ($this->solicitudEditandoId !== null) {
                $solicitud = SolicitudMaterial::with('partidaOriginal')->findOrFail($this->solicitudEditandoId);
                $actualizada = $service->actualizar($solicitud, $this->form, $usuario);
                $this->cerrarModalFormulario();
                $this->mensajeExito = "Solicitud #{$actualizada->id} actualizada correctamente.";

                return;
            }

            if (! is_numeric($this->partidaOriginalId)) {
                $this->addError('partidaOriginalId', 'Selecciona la partida en atención que requiere materiales.');

                return;
            }

            $partida = Partida::findOrFail((int) $this->partidaOriginalId);
            $creada = $service->crearBorrador($partida, $this->form, $usuario);
            $this->cerrarModalFormulario();
            $this->mensajeExito = "Solicitud de materiales #{$creada->id} guardada en borrador. Envía a Dirección cuando esté lista.";
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError("form.{$campo}", $mensaje);
                }
            }
        }
    }

    public function abrirEnviar(int $solicitudId): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $solicitud = SolicitudMaterial::with('partidaOriginal')->findOrFail($solicitudId);
        Gate::authorize('enviar', $solicitud);

        $this->solicitudSeleccionadaId = $solicitud->id;
        $this->respuestaAclaracion = '';
        $this->modalEnviarAbierto = true;
    }

    public function cerrarModalEnviar(): void
    {
        $this->modalEnviarAbierto = false;
        $this->solicitudSeleccionadaId = null;
        $this->respuestaAclaracion = '';
        $this->resetValidation();
    }

    public function ejecutarEnvio(SolicitudMaterialService $service): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $solicitud = SolicitudMaterial::with('partidaOriginal')->findOrFail((int) $this->solicitudSeleccionadaId);
        /** @var User $usuario */
        $usuario = Auth::user();

        $eraRespuesta = $solicitud->estado === 'INFORMACION_REQUERIDA';

        try {
            $actualizada = $service->enviar($solicitud, $usuario, $this->respuestaAclaracion);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $destino = $campo === 'respuesta_aclaracion' ? 'respuestaAclaracion' : "form.{$campo}";
                foreach ($mensajes as $mensaje) {
                    $this->addError($destino, $mensaje);
                }
            }

            return;
        }

        $this->cerrarModalEnviar();
        $this->mensajeExito = $eraRespuesta
            ? "Respuesta enviada a Dirección para la solicitud #{$actualizada->id}."
            : "Solicitud #{$actualizada->id} enviada formalmente a Dirección.";
    }

    public function abrirSolicitarInformacion(int $solicitudId): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $solicitud = SolicitudMaterial::with('partidaOriginal')->findOrFail($solicitudId);
        Gate::authorize('solicitarInformacion', $solicitud);

        $this->solicitudSeleccionadaId = $solicitud->id;
        $this->observacionesDireccion = '';
        $this->modalSolicitarInfoAbierto = true;
    }

    public function cerrarModalSolicitarInfo(): void
    {
        $this->modalSolicitarInfoAbierto = false;
        $this->solicitudSeleccionadaId = null;
        $this->observacionesDireccion = '';
        $this->resetValidation();
    }

    public function ejecutarSolicitudInformacion(SolicitudMaterialService $service): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $solicitud = SolicitudMaterial::with('partidaOriginal')->findOrFail((int) $this->solicitudSeleccionadaId);
        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            $actualizada = $service->solicitarInformacion($solicitud, $usuario, $this->observacionesDireccion);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError('observacionesDireccion', $mensaje);
                }
            }

            return;
        }

        $this->cerrarModalSolicitarInfo();
        $this->mensajeExito = "Se solicitó información complementaria al Jefe para la solicitud #{$actualizada->id}.";
    }

    public function abrirRechazar(int $solicitudId): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $solicitud = SolicitudMaterial::with('partidaOriginal')->findOrFail($solicitudId);
        Gate::authorize('rechazar', $solicitud);

        $this->solicitudSeleccionadaId = $solicitud->id;
        $this->motivoRechazo = '';
        $this->modalRechazarAbierto = true;
    }

    public function cerrarModalRechazar(): void
    {
        $this->modalRechazarAbierto = false;
        $this->solicitudSeleccionadaId = null;
        $this->motivoRechazo = '';
        $this->resetValidation();
    }

    public function ejecutarRechazo(SolicitudMaterialService $service): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $solicitud = SolicitudMaterial::with('partidaOriginal')->findOrFail((int) $this->solicitudSeleccionadaId);
        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            $actualizada = $service->rechazar($solicitud, $usuario, $this->motivoRechazo);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError('motivoRechazo', $mensaje);
                }
            }

            return;
        }

        $this->cerrarModalRechazar();
        $this->mensajeExito = "Solicitud #{$actualizada->id} rechazada. La partida original permanece En atención.";
    }

    public function abrirAutorizar(int $solicitudId): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $solicitud = SolicitudMaterial::with('partidaOriginal')->findOrFail($solicitudId);
        Gate::authorize('autorizar', $solicitud);

        $this->solicitudSeleccionadaId = $solicitud->id;
        $this->autorizacionForm = [
            'tipo_servicio_id' => '',
            'concepto' => (string) $solicitud->descripcion,
            'cantidad_solicitada' => $solicitud->cantidad !== null ? (string) $solicitud->cantidad : '',
            'unidad_medida' => (string) ($solicitud->unidad_medida ?? ''),
            'observaciones_direccion' => '',
        ];
        $this->modalAutorizarAbierto = true;
    }

    public function cerrarModalAutorizar(): void
    {
        $this->modalAutorizarAbierto = false;
        $this->solicitudSeleccionadaId = null;
        $this->resetValidation();
    }

    public function ejecutarAutorizacion(SolicitudMaterialService $service): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $solicitud = SolicitudMaterial::with('partidaOriginal')->findOrFail((int) $this->solicitudSeleccionadaId);
        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            $actualizada = $service->autorizar($solicitud, $this->autorizacionForm, $usuario);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError("autorizacionForm.{$campo}", $mensaje);
                }
            }

            return;
        }

        $numeroNueva = $actualizada->partidaGenerada?->numero_partida;
        $this->cerrarModalAutorizar();
        $this->mensajeExito = "Solicitud #{$actualizada->id} autorizada. Se generó la partida #{$numeroNueva} para Adquisiciones (pendiente de turnar en Partidas).";
    }

    public function abrirDetalle(int $solicitudId): void
    {
        $this->limpiarMensajes();
        $solicitud = SolicitudMaterial::with('partidaOriginal')->findOrFail($solicitudId);
        Gate::authorize('view', $solicitud);

        $this->solicitudSeleccionadaId = $solicitud->id;
        $this->modalDetalleAbierto = true;
    }

    public function cerrarDetalle(): void
    {
        $this->modalDetalleAbierto = false;
        $this->solicitudSeleccionadaId = null;
    }

    public function render(SolicitudMaterialService $service): View
    {
        $usuario = $this->autorizarModulo();
        $esDireccion = $usuario->hasAnyRole(['Super usuario', 'Administrador']);
        $esJefe = $usuario->hasRole('Jefe') && $usuario->departamento_id !== null;
        $puedeCrear = $esJefe && $usuario->checkPermissionTo('solicitudes_materiales.crear', 'web');
        $busqueda = trim($this->buscar);

        $baseQuery = $service->queryVisiblesPara($usuario);

        $solicitudes = (clone $baseQuery)
            ->with([
                'partidaOriginal.servicio',
                'partidaOriginal.departamento',
                'partidaOriginal.folio',
                'partidaOriginal.estado',
                'partidaGenerada.estado',
                'partidaGenerada.folio',
                'partidaGenerada.departamento',
                'solicitante',
                'resolutor',
            ])
            ->when($busqueda !== '', function (Builder $q) use ($busqueda): void {
                $q->where(function (Builder $sub) use ($busqueda): void {
                    $sub->where('descripcion', 'like', "%{$busqueda}%")
                        ->orWhere('justificacion', 'like', "%{$busqueda}%")
                        ->orWhere('observaciones_direccion', 'like', "%{$busqueda}%")
                        ->orWhereHas('partidaOriginal', function (Builder $p) use ($busqueda): void {
                            $p->where('concepto', 'like', "%{$busqueda}%")
                                ->orWhereHas('folio', fn (Builder $f) => $f->where('folio', 'like', "%{$busqueda}%"))
                                ->orWhereHas('servicio', function (Builder $s) use ($busqueda): void {
                                    $s->where('numero_oficio', 'like', "%{$busqueda}%")
                                        ->orWhere('asunto', 'like', "%{$busqueda}%");
                                });
                        });
                });
            })
            ->when($this->estadoFiltro !== '', fn (Builder $q) => $q->where('estado', $this->estadoFiltro))
            ->when($esDireccion && is_numeric($this->departamentoFiltro), function (Builder $q): void {
                $q->whereHas('partidaOriginal', fn (Builder $p) => $p->where('departamento_id', (int) $this->departamentoFiltro));
            })
            ->orderByDesc('id')
            ->paginate(12);

        $solicitudModal = $this->solicitudSeleccionadaId !== null
            ? SolicitudMaterial::with([
                'partidaOriginal.servicio.areaSolicitante',
                'partidaOriginal.departamento',
                'partidaOriginal.folio',
                'partidaOriginal.estado',
                'partidaGenerada.departamento',
                'partidaGenerada.estado',
                'partidaGenerada.folio',
                'solicitante',
                'resolutor',
                'historial.usuario',
            ])->find($this->solicitudSeleccionadaId)
            : null;

        $estadoEnAtencionId = Estado::where('clave', 'EN_ATENCION')->value('id');
        $estadoConcluidoId = Estado::where('clave', 'CONCLUIDO')->value('id');

        $partidasElegibles = ($puedeCrear && $this->modalFormularioAbierto && $this->solicitudEditandoId === null && $estadoEnAtencionId !== null)
            ? Partida::query()
                ->with(['servicio', 'folio', 'departamento'])
                ->where('departamento_id', $usuario->departamento_id)
                ->where('estado_id', $estadoEnAtencionId)
                ->orderByDesc('id')
                ->get()
            : collect();

        $tiposServicioActivos = $this->modalAutorizarAbierto
            ? TipoServicio::where('activo', true)->orderBy('descripcion')->get()
            : collect();

        $tipoSeleccionadoAutorizar = ($this->modalAutorizarAbierto && is_numeric($this->autorizacionForm['tipo_servicio_id'] ?? ''))
            ? $tiposServicioActivos->firstWhere('id', (int) $this->autorizacionForm['tipo_servicio_id'])
            : null;

        return view('livewire.materiales.gestion-solicitudes-materiales', [
            'solicitudes' => $solicitudes,
            'solicitudModal' => $solicitudModal,
            'departamentos' => $esDireccion ? Departamento::orderBy('nombre')->get() : collect(),
            'partidasElegibles' => $partidasElegibles,
            'tiposServicioActivos' => $tiposServicioActivos,
            'tipoSeleccionadoAutorizar' => $tipoSeleccionadoAutorizar,
            'esDireccion' => $esDireccion,
            'esJefe' => $esJefe,
            'puedeCrear' => $puedeCrear,
            'resumen' => [
                'total' => (clone $baseQuery)->count(),
                'enviadas' => (clone $baseQuery)->where('estado', 'ENVIADA')->count(),
                'borradores_aclaracion' => (clone $baseQuery)->whereIn('estado', ['BORRADOR', 'INFORMACION_REQUERIDA'])->count(),
                'autorizadas' => (clone $baseQuery)->where('estado', 'AUTORIZADA')->count(),
                'rechazadas' => (clone $baseQuery)->where('estado', 'RECHAZADA')->count(),
                'adq_concluidas' => $estadoConcluidoId !== null
                    ? (clone $baseQuery)
                        ->where('estado', 'AUTORIZADA')
                        ->whereHas('partidaGenerada', fn (Builder $p) => $p->where('estado_id', $estadoConcluidoId))
                        ->count()
                    : 0,
            ],
        ]);
    }

    private function autorizarModulo(): User
    {
        /** @var User|null $usuario */
        $usuario = Auth::user();
        abort_unless(
            $usuario !== null && $usuario->activo && $usuario->checkPermissionTo('solicitudes_materiales.ver', 'web'),
            403
        );

        return $usuario;
    }

    private function limpiarMensajes(): void
    {
        $this->mensajeExito = null;
        $this->mensajeError = null;
    }
}
