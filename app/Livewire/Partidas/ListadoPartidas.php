<?php

namespace App\Livewire\Partidas;

use App\Models\Departamento;
use App\Models\Estado;
use App\Models\Operativo;
use App\Models\OperativoPartida;
use App\Models\Partida;
use App\Models\TipoServicio;
use App\Models\User;
use App\Services\ExpedienteService;
use App\Services\PartidaService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ListadoPartidas extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $buscar = '';

    public string $departamentoFiltro = '';

    public string $estadoFiltro = '';

    public string $ejercicioFiltro = '';

    #[Url(as: 'expediente', except: '')]
    public string $expedienteFiltro = '';

    public bool $modalEditarPreparacionAbierto = false;

    public bool $modalTurnadoAbierto = false;

    public bool $modalCancelarAbierto = false;

    public bool $modalRegresarDireccionAbierto = false;

    public bool $modalOperativosAbierto = false;

    public bool $modalDevolverAbierto = false;

    public bool $modalReanudarAbierto = false;

    public bool $modalConcluirAbierto = false;

    public bool $modalDetalleAbierto = false;

    public string $modoTurnado = 'turnar';

    public ?int $partidaSeleccionadaId = null;

    public string $departamentoDestinoId = '';

    public string $motivoTurnado = '';

    public string $motivoCancelacion = '';

    public string $motivoRegresoDireccion = '';

    public string $operativoAsignarId = '';

    public ?int $asignacionSustituirId = null;

    public string $operativoSustitutoId = '';

    public string $observacionesDevolucion = '';

    public string $respuestaSubsanacion = '';

    /** @var array<string, mixed> */
    public array $preparacionForm = [
        'tipo_servicio_id' => '',
        'concepto' => '',
        'cantidad_solicitada' => '',
        'unidad_medida' => '',
        'departamento_id' => '',
        'observaciones' => '',
    ];

    /** @var array<string, mixed> */
    public array $conclusionForm = [
        'descripcion_trabajo' => '',
        'descripcion_materiales' => '',
        'cantidad_atendida' => '',
        'conformidad_recibida' => true,
        'recibe_nombre' => '',
        'recibe_cargo' => '',
        'motivo_sin_conformidad' => '',
        'nombre_quien_recibe_material' => '',
    ];

    /** @var array<int, mixed> */
    public array $evidenciasConclusion = [];

    public mixed $archivoConformidad = null;

    public ?string $mensajeExito = null;

    public ?string $mensajeError = null;

    public function mount(): void
    {
        $this->autorizarListado();
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedDepartamentoFiltro(): void
    {
        $this->resetPage();
    }

    public function updatedEstadoFiltro(): void
    {
        $this->resetPage();
    }

    public function updatedEjercicioFiltro(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltroExpediente(): void
    {
        $this->expedienteFiltro = '';
        $this->resetPage();
    }

    public function abrirEditarPreparacion(int $partidaId): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::findOrFail($partidaId);
        Gate::authorize('update', $partida);

        $this->modalTurnadoAbierto = false;
        $this->partidaSeleccionadaId = $partida->id;
        $this->preparacionForm = [
            'tipo_servicio_id' => $partida->tipo_servicio_id !== null ? (string) $partida->tipo_servicio_id : '',
            'concepto' => (string) ($partida->concepto ?? ''),
            'cantidad_solicitada' => $partida->cantidad_solicitada !== null ? (string) $partida->cantidad_solicitada : '',
            'unidad_medida' => (string) ($partida->unidad_medida ?? ''),
            'departamento_id' => $partida->departamento_id !== null ? (string) $partida->departamento_id : '',
            'observaciones' => (string) ($partida->observaciones ?? ''),
        ];
        $this->modalEditarPreparacionAbierto = true;
    }

    public function cerrarModalEditarPreparacion(): void
    {
        $this->modalEditarPreparacionAbierto = false;
        $this->partidaSeleccionadaId = null;
        $this->resetValidation();
    }

    public function guardarPreparacion(ExpedienteService $expedienteService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::findOrFail((int) $this->partidaSeleccionadaId);
        Gate::authorize('update', $partida);

        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            $actualizada = $expedienteService->actualizarPartidaPreparacion($partida, $this->preparacionForm, $usuario);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError("preparacionForm.{$campo}", $mensaje);
                }
            }

            return;
        }

        $this->cerrarModalEditarPreparacion();
        $this->mensajeExito = "Datos de preparación de la partida #{$actualizada->numero_partida} actualizados correctamente.";
    }

    public function abrirDetalle(int $partidaId): void
    {
        $this->limpiarMensajes();
        $partida = Partida::findOrFail($partidaId);
        Gate::authorize('view', $partida);

        $this->partidaSeleccionadaId = $partida->id;
        $this->modalDetalleAbierto = true;
    }

    public function cerrarDetalle(): void
    {
        $this->modalDetalleAbierto = false;
        $this->partidaSeleccionadaId = null;
    }

    public function abrirTurnar(int $partidaId): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::with('servicio')->findOrFail($partidaId);
        Gate::authorize('view', $partida);

        $destinos = $this->destinosPermitidosPara($partida, 'turnar');
        abort_if($destinos->isEmpty(), 403);

        $this->partidaSeleccionadaId = $partida->id;
        $this->modoTurnado = 'turnar';
        $this->motivoTurnado = '';

        $propuesto = $partida->departamento_id !== null
            ? $destinos->firstWhere('id', $partida->departamento_id)
            : null;
        $this->departamentoDestinoId = $propuesto !== null
            ? (string) $propuesto->id
            : ($destinos->count() === 1 ? (string) $destinos->first()->id : '');

        $this->modalTurnadoAbierto = true;
    }

    public function abrirReturnar(int $partidaId): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::with('servicio')->findOrFail($partidaId);
        Gate::authorize('view', $partida);

        $destinos = $this->destinosPermitidosPara($partida, 'returnar');
        abort_if($destinos->isEmpty(), 403);

        $this->partidaSeleccionadaId = $partida->id;
        $this->modoTurnado = 'returnar';
        $this->motivoTurnado = '';
        $this->departamentoDestinoId = '';
        $this->modalTurnadoAbierto = true;
    }

    public function cerrarModalTurnado(): void
    {
        $this->modalTurnadoAbierto = false;
        $this->partidaSeleccionadaId = null;
        $this->departamentoDestinoId = '';
        $this->motivoTurnado = '';
        $this->resetValidation();
    }

    public function ejecutarTurnado(PartidaService $partidaService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        if (! is_numeric($this->departamentoDestinoId)) {
            $this->addError('departamentoDestinoId', 'Selecciona el departamento destino.');

            return;
        }

        $partida = Partida::findOrFail((int) $this->partidaSeleccionadaId);
        $destino = Departamento::findOrFail((int) $this->departamentoDestinoId);
        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            if ($this->modoTurnado === 'returnar') {
                $actualizada = $partidaService->returnar($partida, $destino, $usuario, $this->motivoTurnado);
                $accion = 're-turnada';
            } else {
                $actualizada = $partidaService->turnar($partida, $destino, $usuario, $this->motivoTurnado);
                $accion = 'turnada';
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $destinoError = $campo === 'partida' ? 'departamentoDestinoId' : 'motivoTurnado';
                foreach ($mensajes as $mensaje) {
                    $this->addError($destinoError, $mensaje);
                }
            }

            return;
        }

        $folio = $actualizada->folio?->folio ?? '';
        $this->cerrarModalTurnado();
        $this->mensajeExito = "Partida #{$actualizada->numero_partida} {$accion} a {$destino->siglas} con folio {$folio}.";
    }

    public function abrirRegresarDireccion(int $partidaId): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::findOrFail($partidaId);
        Gate::authorize('regresarDireccion', $partida);

        $this->partidaSeleccionadaId = $partida->id;
        $this->motivoRegresoDireccion = '';
        $this->modalRegresarDireccionAbierto = true;
    }

    public function cerrarModalRegresarDireccion(): void
    {
        $this->modalRegresarDireccionAbierto = false;
        $this->partidaSeleccionadaId = null;
        $this->motivoRegresoDireccion = '';
        $this->resetValidation();
    }

    public function ejecutarRegresoDireccion(PartidaService $partidaService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::findOrFail((int) $this->partidaSeleccionadaId);
        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            $actualizada = $partidaService->regresarADireccion($partida, $usuario, $this->motivoRegresoDireccion);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError('motivoRegresoDireccion', $mensaje);
                }
            }

            return;
        }

        $this->cerrarModalRegresarDireccion();
        $this->mensajeExito = "Partida #{$actualizada->numero_partida} regresada a Dirección (En revisión) para re-turnado.";
    }

    public function abrirOperativos(int $partidaId): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::findOrFail($partidaId);
        Gate::authorize('view', $partida);
        abort_unless($this->puedeGestionarOperativos($partida), 403);

        $this->partidaSeleccionadaId = $partida->id;
        $this->operativoAsignarId = '';
        $this->asignacionSustituirId = null;
        $this->operativoSustitutoId = '';
        $this->modalOperativosAbierto = true;
    }

    public function cerrarModalOperativos(): void
    {
        $this->modalOperativosAbierto = false;
        $this->partidaSeleccionadaId = null;
        $this->operativoAsignarId = '';
        $this->asignacionSustituirId = null;
        $this->operativoSustitutoId = '';
        $this->resetValidation();
    }

    public function asignarOperativo(PartidaService $partidaService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        if (! is_numeric($this->operativoAsignarId)) {
            $this->addError('operativoAsignarId', 'Selecciona un operativo para asignar.');

            return;
        }

        $partida = Partida::findOrFail((int) $this->partidaSeleccionadaId);
        $operativo = Operativo::findOrFail((int) $this->operativoAsignarId);
        /** @var User $usuario */
        $usuario = Auth::user();

        $partidaService->asignarOperativo($partida, $operativo, $usuario);
        $this->operativoAsignarId = '';
        $this->mensajeExito = "Se asignó a {$operativo->nombre} en la partida #{$partida->numero_partida}.";
    }

    public function finalizarAsignacion(int $asignacionId, PartidaService $partidaService): void
    {
        $this->limpiarMensajes();
        $asignacion = OperativoPartida::with(['operativo', 'partida'])->findOrFail($asignacionId);
        /** @var User $usuario */
        $usuario = Auth::user();

        $partidaService->finalizarAsignacion($asignacion, $usuario);
        $this->asignacionSustituirId = null;
        $this->mensajeExito = "Se finalizó la asignación de {$asignacion->operativo?->nombre}.";
    }

    public function iniciarSustitucion(int $asignacionId): void
    {
        $this->resetValidation();
        $this->asignacionSustituirId = $asignacionId;
        $this->operativoSustitutoId = '';
    }

    public function ejecutarSustitucion(PartidaService $partidaService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        if ($this->asignacionSustituirId === null || ! is_numeric($this->operativoSustitutoId)) {
            $this->addError('operativoSustitutoId', 'Selecciona el operativo sustituto.');

            return;
        }

        $anterior = OperativoPartida::with('partida')->findOrFail($this->asignacionSustituirId);
        $nuevo = Operativo::findOrFail((int) $this->operativoSustitutoId);
        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            $partidaService->sustituirOperativo($anterior, $nuevo, $usuario);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError('operativoSustitutoId', $mensaje);
                }
            }

            return;
        }

        $this->asignacionSustituirId = null;
        $this->operativoSustitutoId = '';
        $this->mensajeExito = "Operativo sustituido por {$nuevo->nombre}.";
    }

    public function iniciarAtencion(int $partidaId, PartidaService $partidaService): void
    {
        $this->limpiarMensajes();
        $partida = Partida::findOrFail($partidaId);
        /** @var User $usuario */
        $usuario = Auth::user();

        $actualizada = $partidaService->iniciarAtencion($partida, $usuario);
        $this->mensajeExito = "La partida #{$actualizada->numero_partida} pasó a estado En atención.";
    }

    public function abrirDevolver(int $partidaId): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::findOrFail($partidaId);
        Gate::authorize('devolver', $partida);

        $this->partidaSeleccionadaId = $partida->id;
        $this->observacionesDevolucion = '';
        $this->modalDevolverAbierto = true;
    }

    public function cerrarModalDevolver(): void
    {
        $this->modalDevolverAbierto = false;
        $this->partidaSeleccionadaId = null;
        $this->observacionesDevolucion = '';
        $this->resetValidation();
    }

    public function ejecutarDevolucion(PartidaService $partidaService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::findOrFail((int) $this->partidaSeleccionadaId);
        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            $partidaService->devolverAlArea($partida, $usuario, $this->observacionesDevolucion);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError('observacionesDevolucion', $mensaje);
                }
            }

            return;
        }

        $this->cerrarModalDevolver();
        $this->mensajeExito = "Partida #{$partida->numero_partida} devuelta al área solicitante.";
    }

    public function abrirReanudar(int $partidaId): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::findOrFail($partidaId);
        Gate::authorize('reanudar', $partida);

        $this->partidaSeleccionadaId = $partida->id;
        $this->respuestaSubsanacion = '';
        $this->modalReanudarAbierto = true;
    }

    public function cerrarModalReanudar(): void
    {
        $this->modalReanudarAbierto = false;
        $this->partidaSeleccionadaId = null;
        $this->respuestaSubsanacion = '';
        $this->resetValidation();
    }

    public function ejecutarReanudacion(PartidaService $partidaService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::findOrFail((int) $this->partidaSeleccionadaId);
        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            $actualizada = $partidaService->reanudarDesdeDevolucion($partida, $usuario, $this->respuestaSubsanacion);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError('respuestaSubsanacion', $mensaje);
                }
            }

            return;
        }

        $this->cerrarModalReanudar();
        $this->mensajeExito = "Subsanación registrada; la partida #{$actualizada->numero_partida} recuperó estado {$actualizada->estado?->nombre}.";
    }

    public function abrirConcluir(int $partidaId): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::with(['departamento', 'partidaOrigen'])->findOrFail($partidaId);
        Gate::authorize('concluir', $partida);

        $this->partidaSeleccionadaId = $partida->id;
        $this->conclusionForm = [
            'descripcion_trabajo' => '',
            'descripcion_materiales' => '',
            'cantidad_atendida' => $partida->cantidad_solicitada !== null ? (string) $partida->cantidad_solicitada : '',
            'conformidad_recibida' => true,
            'recibe_nombre' => '',
            'recibe_cargo' => '',
            'motivo_sin_conformidad' => '',
            'nombre_quien_recibe_material' => '',
        ];
        $this->evidenciasConclusion = [];
        $this->archivoConformidad = null;
        $this->modalConcluirAbierto = true;
    }

    public function cerrarModalConcluir(): void
    {
        $this->modalConcluirAbierto = false;
        $this->partidaSeleccionadaId = null;
        $this->evidenciasConclusion = [];
        $this->archivoConformidad = null;
        $this->resetValidation();
    }

    public function ejecutarConclusion(PartidaService $partidaService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::with(['departamento', 'partidaOrigen'])->findOrFail((int) $this->partidaSeleccionadaId);
        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            $partidaService->concluir(
                $partida,
                $this->conclusionForm,
                $usuario,
                $this->evidenciasConclusion,
                $this->archivoConformidad,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $clave = match (true) {
                    $campo === 'evidencias' || str_starts_with($campo, 'evidencias.') => 'evidenciasConclusion',
                    $campo === 'archivo_conformidad' => 'archivoConformidad',
                    default => "conclusionForm.{$campo}",
                };
                foreach ($mensajes as $mensaje) {
                    $this->addError($clave, $mensaje);
                }
            }

            return;
        }

        $this->cerrarModalConcluir();
        $this->mensajeExito = "Partida #{$partida->numero_partida} concluida correctamente.";
    }

    public function abrirCancelar(int $partidaId): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::findOrFail($partidaId);
        Gate::authorize('cancelar', $partida);

        $this->partidaSeleccionadaId = $partida->id;
        $this->motivoCancelacion = '';
        $this->modalCancelarAbierto = true;
    }

    public function cerrarModalCancelar(): void
    {
        $this->modalCancelarAbierto = false;
        $this->partidaSeleccionadaId = null;
        $this->motivoCancelacion = '';
        $this->resetValidation();
    }

    public function ejecutarCancelacion(PartidaService $partidaService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $partida = Partida::findOrFail((int) $this->partidaSeleccionadaId);
        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            $actualizada = $partidaService->cancelar($partida, $usuario, $this->motivoCancelacion);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError('motivoCancelacion', $mensaje);
                }
            }

            return;
        }

        $this->cerrarModalCancelar();
        $this->mensajeExito = "Partida #{$actualizada->numero_partida} cancelada correctamente.";
    }

    public function render(PartidaService $partidaService): View
    {
        $usuario = $this->autorizarListado();
        $busqueda = trim($this->buscar);

        $baseQuery = $partidaService->queryVisiblesPara($usuario);

        $partidas = (clone $baseQuery)
            ->with([
                'servicio.areaSolicitante',
                'tipoServicio',
                'departamento',
                'partidaOrigen',
                'estado',
                'folio',
                'registroConclusion',
                'asignaciones' => fn ($q) => $q->where('activo', true)->with('operativo'),
                'devoluciones' => fn ($q) => $q->whereNull('resultado_cierre'),
            ])
            ->when($busqueda !== '', function (Builder $q) use ($busqueda): void {
                $q->where(function (Builder $sub) use ($busqueda): void {
                    $sub->where('concepto', 'like', "%{$busqueda}%")
                        ->orWhereHas('folio', fn (Builder $f) => $f->where('folio', 'like', "%{$busqueda}%"))
                        ->orWhereHas('servicio', function (Builder $s) use ($busqueda): void {
                            $s->where('numero_oficio', 'like', "%{$busqueda}%")
                                ->orWhere('asunto', 'like', "%{$busqueda}%");
                        });
                });
            })
            ->when($this->departamentoFiltro === 'sin_departamento', fn (Builder $q) => $q->whereNull('departamento_id'))
            ->when(is_numeric($this->departamentoFiltro), fn (Builder $q) => $q->where('departamento_id', (int) $this->departamentoFiltro))
            ->when($this->estadoFiltro !== '', fn (Builder $q) => $q->where('estado_id', (int) $this->estadoFiltro))
            ->when($this->ejercicioFiltro !== '', function (Builder $q): void {
                $q->whereHas('servicio', fn (Builder $s) => $s->where('ejercicio', (int) $this->ejercicioFiltro));
            })
            ->when(is_numeric($this->expedienteFiltro), fn (Builder $q) => $q->where('servicio_id', (int) $this->expedienteFiltro))
            ->orderByDesc('id')
            ->paginate(12);

        $departamentos = Departamento::orderBy('nombre')->get();
        $departamentosActivos = $departamentos->where('activo', true)->values();
        $accionesPorPartida = $this->calcularAccionesPorPartida($partidas->getCollection(), $usuario, $departamentosActivos);

        $partidaModal = $this->partidaSeleccionadaId !== null
            ? Partida::with([
                'servicio.areaSolicitante',
                'tipoServicio',
                'departamento',
                'partidaOrigen',
                'estado',
                'folio',
                'usuarioCancelacion',
                'turnados.departamentoOrigen',
                'turnados.departamentoDestino',
                'turnados.folioOrigen',
                'turnados.folioDestino',
                'turnados.usuario',
                'asignaciones.operativo',
                'devoluciones.usuario',
                'devoluciones.estadoAnterior',
                'devoluciones.usuarioReanudacion',
                'devoluciones.usuarioCierre',
                'registroConclusion.usuario',
                'solicitudesMateriales.solicitante',
                'solicitudesMateriales.partidaGenerada.estado',
                'solicitudesMateriales.partidaGenerada.folio',
            ])->find($this->partidaSeleccionadaId)
            : null;

        $destinosTurnado = ($this->modalTurnadoAbierto && $partidaModal !== null)
            ? $this->destinosPermitidosPara($partidaModal, $this->modoTurnado, $departamentosActivos)
            : collect();

        $operativosDisponibles = ($this->modalOperativosAbierto && $partidaModal !== null && $partidaModal->departamento_id !== null)
            ? Operativo::query()
                ->where('departamento_id', $partidaModal->departamento_id)
                ->where('activo', true)
                ->whereNotIn(
                    'id',
                    $partidaModal->asignaciones->where('activo', true)->pluck('operativo_id')
                )
                ->orderBy('nombre')
                ->get()
            : collect();

        $estadosIndexados = Estado::pluck('id', 'clave');
        $ejercicios = (clone $baseQuery)
            ->join('servicios', 'servicios.id', '=', 'partidas.servicio_id')
            ->select('servicios.ejercicio')
            ->distinct()
            ->orderByDesc('servicios.ejercicio')
            ->pluck('servicios.ejercicio');

        $tiposServicioActivos = $this->modalEditarPreparacionAbierto
            ? TipoServicio::where('activo', true)->orderBy('descripcion')->get()
            : collect();
        $tipoPreparacion = ($this->modalEditarPreparacionAbierto && is_numeric($this->preparacionForm['tipo_servicio_id'] ?? ''))
            ? $tiposServicioActivos->firstWhere('id', (int) $this->preparacionForm['tipo_servicio_id'])
            : null;

        return view('livewire.partidas.listado-partidas', [
            'partidas' => $partidas,
            'accionesPorPartida' => $accionesPorPartida,
            'departamentos' => $departamentos,
            'departamentosActivos' => $departamentosActivos,
            'tiposServicioActivos' => $tiposServicioActivos,
            'requiereCantidadPreparacion' => $tipoPreparacion?->requiere_cantidad ?? false,
            'estados' => Estado::orderBy('id')->get(),
            'ejercicios' => $ejercicios,
            'partidaModal' => $partidaModal,
            'destinosTurnado' => $destinosTurnado,
            'operativosDisponibles' => $operativosDisponibles,
            'esEntregaAdqModal' => $partidaModal !== null && $partidaService->esEntregaAdquisiciones($partidaModal),
            'resumen' => [
                'total' => (clone $baseQuery)->count(),
                'pendientes_turnar' => (clone $baseQuery)
                    ->whereNull('servicio_folio_id')
                    ->whereIn('estado_id', array_filter([
                        $estadosIndexados['REGISTRADO'] ?? null,
                        $estadosIndexados['EN_REVISION'] ?? null,
                    ]))
                    ->count(),
                'en_proceso' => (clone $baseQuery)
                    ->whereIn('estado_id', array_filter([
                        $estadosIndexados['TURNADO'] ?? null,
                        $estadosIndexados['EN_ATENCION'] ?? null,
                        $estadosIndexados['DEVUELTO_AREA'] ?? null,
                    ]))
                    ->count(),
                'concluidas' => (clone $baseQuery)
                    ->where('estado_id', $estadosIndexados['CONCLUIDO'] ?? 0)
                    ->count(),
            ],
        ]);
    }

    public function puedeGestionarOperativos(Partida $partida): bool
    {
        /** @var User|null $usuario */
        $usuario = Auth::user();
        if ($usuario === null || ! $usuario->activo) {
            return false;
        }

        if (! $usuario->checkPermissionTo('operativos_partida.asignar', 'web')
            && ! $usuario->checkPermissionTo('operativos_partida.finalizar', 'web')) {
            return false;
        }

        $claveEstado = $partida->estado?->clave;
        if (! in_array($claveEstado, ['TURNADO', 'EN_ATENCION'], true)) {
            return false;
        }

        if ($usuario->hasAnyRole(['Super usuario', 'Administrador'])) {
            return $partida->departamento_id !== null;
        }

        return $usuario->departamento_id !== null
            && $usuario->hasAnyRole(['Jefe', 'Recepción/Coordinador'])
            && (int) $partida->departamento_id === (int) $usuario->departamento_id;
    }

    /**
     * Evalúa banderas de acciones por fila en memoria usando relaciones ya cargadas (evita ~150+ queries N+1).
     * Cada acción del componente vuelve a autorizar en el servidor con Gate::authorize().
     *
     * @param  Collection<int, Partida>  $partidas
     * @param  Collection<int, Departamento>  $departamentosActivos
     * @return array<int, array<string, bool>>
     */
    private function calcularAccionesPorPartida(
        Collection $partidas,
        User $usuario,
        Collection $departamentosActivos,
    ): array {
        $esGeneral = $usuario->hasAnyRole(['Super usuario', 'Administrador']);
        $esAdmin = $usuario->hasRole('Administrador');
        $esJefe = $usuario->hasRole('Jefe');
        $esRecepcionCoord = $usuario->hasRole('Recepción/Coordinador');
        $esOperativo = $usuario->hasRole('Operativo');
        $esRecepcionCentral = $usuario->departamento_id === null && $esRecepcionCoord;

        $permEditar = $usuario->checkPermissionTo('partidas.editar', 'web');
        $permTurnar = $usuario->checkPermissionTo('partidas.turnar', 'web');
        $permReturnar = $usuario->checkPermissionTo('partidas.returnar', 'web');
        $permRegresarDir = $usuario->checkPermissionTo('partidas.regresar_direccion', 'web');
        $permIniciar = $usuario->checkPermissionTo('partidas.iniciar_atencion', 'web');
        $permDevolver = $usuario->checkPermissionTo('partidas.devolver', 'web');
        $permReanudar = $usuario->checkPermissionTo('partidas.reanudar', 'web');
        $permConcluir = $usuario->checkPermissionTo('partidas.concluir', 'web');
        $permCancelar = $usuario->checkPermissionTo('partidas.cancelar', 'web');

        $deptoUsuarioActivo = $usuario->departamento_id !== null
            && $departamentosActivos->contains('id', $usuario->departamento_id);

        $mapa = [];

        foreach ($partidas as $partida) {
            $estadoClave = $partida->estado?->clave;
            $sinFolio = $partida->servicio_folio_id === null;
            $mismoDepto = $usuario->departamento_id !== null
                && (int) $partida->departamento_id === (int) $usuario->departamento_id;
            $esViaRapida = (bool) $partida->servicio?->es_via_rapida;
            $tieneDevolucionAbierta = $partida->devoluciones->isNotEmpty();
            $tieneAsignacionPropia = $esOperativo && $partida->asignaciones->contains(
                fn (OperativoPartida $a): bool => $a->activo
                    && $a->fecha_finalizacion === null
                    && (int) $a->operativo?->user_id === (int) $usuario->id
            );
            $alcanceOperativo = $esGeneral
                || ($mismoDepto && ($esJefe || $esRecepcionCoord))
                || $tieneAsignacionPropia;
            $alcanceDepartamental = $esGeneral || ($mismoDepto && ($esJefe || $esRecepcionCoord));

            $mapa[$partida->id] = [
                'editar' => $permEditar
                    && $sinFolio
                    && in_array($estadoClave, ['REGISTRADO', 'EN_REVISION'], true)
                    && ($esGeneral || $esRecepcionCentral || ($esRecepcionCoord && $mismoDepto && $esViaRapida)),
                'turnar' => $permTurnar
                    && $departamentosActivos->isNotEmpty()
                    && $sinFolio
                    && in_array($estadoClave, ['REGISTRADO', 'EN_REVISION'], true)
                    && ($esGeneral || ($esRecepcionCoord && $mismoDepto && $deptoUsuarioActivo && $esViaRapida)),
                'returnar' => $permReturnar
                    && $esGeneral
                    && $partida->departamento_id !== null
                    && ! $sinFolio
                    && in_array($estadoClave, ['TURNADO', 'EN_ATENCION', 'EN_REVISION'], true)
                    && $departamentosActivos->contains(fn (Departamento $d): bool => (int) $d->id !== (int) $partida->departamento_id),
                'regresar_direccion' => $permRegresarDir
                    && $esJefe
                    && $mismoDepto
                    && ! $sinFolio
                    && in_array($estadoClave, ['TURNADO', 'EN_ATENCION'], true),
                'operativos' => $this->puedeGestionarOperativos($partida),
                'iniciar' => $permIniciar
                    && $partida->departamento_id !== null
                    && ! $sinFolio
                    && $estadoClave === 'TURNADO'
                    && $alcanceOperativo,
                'devolver' => $permDevolver
                    && in_array($estadoClave, ['TURNADO', 'EN_ATENCION'], true)
                    && ! $tieneDevolucionAbierta
                    && $alcanceDepartamental,
                'reanudar' => $permReanudar
                    && $estadoClave === 'DEVUELTO_AREA'
                    && $tieneDevolucionAbierta
                    && $alcanceDepartamental,
                'concluir' => $permConcluir
                    && $estadoClave === 'EN_ATENCION'
                    && $partida->registroConclusion === null
                    && $alcanceOperativo,
                'cancelar' => $permCancelar
                    && in_array($estadoClave, ['REGISTRADO', 'EN_REVISION', 'TURNADO', 'EN_ATENCION', 'DEVUELTO_AREA'], true)
                    && ($estadoClave !== 'DEVUELTO_AREA' || $tieneDevolucionAbierta)
                    && ($esAdmin || ($esJefe && $mismoDepto && ($estadoClave !== 'REGISTRADO' || ! $sinFolio))),
            ];
        }

        return $mapa;
    }

    /**
     * @param  Collection<int, Departamento>|null  $departamentosActivos
     * @return Collection<int, Departamento>
     */
    private function destinosPermitidosPara(Partida $partida, string $ability, ?Collection $departamentosActivos = null): Collection
    {
        $lista = $departamentosActivos ?? Departamento::where('activo', true)->orderBy('nombre')->get();

        return $lista->filter(fn (Departamento $depto): bool => Gate::allows($ability, [$partida, $depto]))->values();
    }

    private function autorizarListado(): User
    {
        /** @var User|null $usuario */
        $usuario = Auth::user();
        abort_unless(
            $usuario !== null && $usuario->activo && $usuario->checkPermissionTo('partidas.ver', 'web'),
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
