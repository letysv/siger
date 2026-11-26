<?php

namespace App\Livewire\Expedientes;

use App\Models\Area;
use App\Models\Departamento;
use App\Models\Partida;
use App\Models\Servicio;
use App\Models\ServicioAcuse;
use App\Models\TipoServicio;
use App\Models\User;
use App\Services\ExpedienteService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class DetalleExpediente extends Component
{
    use WithFileUploads;

    public Servicio $servicio;

    public bool $modalEditarExpedienteAbierto = false;

    public bool $modalPartidaAbierto = false;

    public ?int $partidaEditandoId = null;

    public bool $modalAcuseAbierto = false;

    public ?int $acuseOriginalId = null;

    public string $motivoSustitucion = '';

    public mixed $nuevoArchivo = null;

    public mixed $nuevoAcuse = null;

    /** @var array<string, mixed> */
    public array $expedienteForm = [
        'numero_oficio' => '',
        'fecha_oficio' => '',
        'fecha_recepcion' => '',
        'area_solicitante_id' => '',
        'nombre_solicitante' => '',
        'dirigido_a' => '',
        'asunto' => '',
        'descripcion' => '',
    ];

    /** @var array<string, mixed> */
    public array $partidaForm = [
        'tipo_servicio_id' => '',
        'concepto' => '',
        'cantidad_solicitada' => '',
        'unidad_medida' => '',
        'departamento_id' => '',
        'observaciones' => '',
    ];

    public ?string $mensajeExito = null;

    public ?string $mensajeError = null;

    public function mount(Servicio $servicio): void
    {
        Gate::authorize('view', $servicio);
        $this->servicio = $servicio;
    }

    public function abrirEditarExpediente(): void
    {
        $expediente = Servicio::findOrFail($this->servicio->getKey());
        Gate::authorize('update', $expediente);

        $this->limpiarMensajes();
        $this->resetValidation();
        $this->expedienteForm = [
            'numero_oficio' => (string) ($expediente->numero_oficio ?? ''),
            'fecha_oficio' => $expediente->fecha_oficio ? $expediente->fecha_oficio->format('Y-m-d') : '',
            'fecha_recepcion' => $expediente->fecha_recepcion ? $expediente->fecha_recepcion->format('Y-m-d\TH:i') : '',
            'area_solicitante_id' => (string) $expediente->area_solicitante_id,
            'nombre_solicitante' => (string) $expediente->nombre_solicitante,
            'dirigido_a' => (string) $expediente->dirigido_a,
            'asunto' => (string) $expediente->asunto,
            'descripcion' => (string) ($expediente->descripcion ?? ''),
        ];
        $this->modalEditarExpedienteAbierto = true;
    }

    public function cerrarModalEditarExpediente(): void
    {
        $this->modalEditarExpedienteAbierto = false;
        $this->resetValidation();
    }

    public function guardarExpediente(ExpedienteService $expedienteService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $expediente = Servicio::findOrFail($this->servicio->getKey());
        Gate::authorize('update', $expediente);

        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            $this->servicio = $expedienteService->actualizarExpediente($expediente, $this->expedienteForm, $usuario);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError("expedienteForm.{$campo}", $mensaje);
                }
            }

            return;
        }

        $this->cerrarModalEditarExpediente();
        $this->mensajeExito = 'Los datos generales del expediente se actualizaron correctamente.';
    }

    public function abrirModalPartida(): void
    {
        Gate::authorize('create', [Partida::class, $this->servicio]);

        $this->limpiarMensajes();
        $this->resetValidation();
        $this->partidaEditandoId = null;
        $this->partidaForm = [
            'tipo_servicio_id' => '',
            'concepto' => '',
            'cantidad_solicitada' => '',
            'unidad_medida' => '',
            'departamento_id' => '',
            'observaciones' => '',
        ];
        $this->modalPartidaAbierto = true;
    }

    public function abrirEditarPartida(int $partidaId): void
    {
        $partida = $this->servicio->partidas()->findOrFail($partidaId);
        Gate::authorize('update', $partida);

        $this->limpiarMensajes();
        $this->resetValidation();
        $this->partidaEditandoId = $partida->id;
        $this->partidaForm = [
            'tipo_servicio_id' => $partida->tipo_servicio_id !== null ? (string) $partida->tipo_servicio_id : '',
            'concepto' => (string) ($partida->concepto ?? ''),
            'cantidad_solicitada' => $partida->cantidad_solicitada !== null ? (string) $partida->cantidad_solicitada : '',
            'unidad_medida' => (string) ($partida->unidad_medida ?? ''),
            'departamento_id' => $partida->departamento_id !== null ? (string) $partida->departamento_id : '',
            'observaciones' => (string) ($partida->observaciones ?? ''),
        ];
        $this->modalPartidaAbierto = true;
    }

    public function cerrarModalPartida(): void
    {
        $this->modalPartidaAbierto = false;
        $this->partidaEditandoId = null;
        $this->resetValidation();
    }

    public function guardarPartida(ExpedienteService $expedienteService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        /** @var User $usuario */
        $usuario = Auth::user();
        $editando = $this->partidaEditandoId !== null
            ? $this->servicio->partidas()->findOrFail($this->partidaEditandoId)
            : null;

        if ($editando !== null) {
            Gate::authorize('update', $editando);
        } else {
            Gate::authorize('create', [Partida::class, $this->servicio]);
        }

        try {
            $partida = $editando !== null
                ? $expedienteService->actualizarPartidaPreparacion($editando, $this->partidaForm, $usuario)
                : $expedienteService->agregarPartida($this->servicio, $this->partidaForm, $usuario);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError("partidaForm.{$campo}", $mensaje);
                }
            }

            return;
        }

        $accion = $editando !== null ? 'actualizó' : 'agregó';
        $this->cerrarModalPartida();
        $this->mensajeExito = "Se {$accion} la partida #{$partida->numero_partida} del expediente.";
    }

    public function subirArchivo(ExpedienteService $expedienteService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();
        Gate::authorize('adjuntarDocumento', $this->servicio);

        if ($this->nuevoArchivo === null) {
            $this->addError('nuevoArchivo', 'Selecciona un archivo para adjuntar.');

            return;
        }

        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            $expedienteService->adjuntarArchivo($this->servicio, $this->nuevoArchivo, $usuario);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->addError('nuevoArchivo', $mensaje);
                }
            }

            return;
        }

        $this->nuevoArchivo = null;
        $this->mensajeExito = 'El archivo se adjuntó correctamente al expediente.';
    }

    public function abrirModalAcuse(?int $acuseOriginalId = null): void
    {
        Gate::authorize('adjuntarDocumento', $this->servicio);

        $this->limpiarMensajes();
        $this->resetValidation();
        $this->acuseOriginalId = $acuseOriginalId;
        $this->motivoSustitucion = '';
        $this->nuevoAcuse = null;
        $this->modalAcuseAbierto = true;
    }

    public function cerrarModalAcuse(): void
    {
        $this->modalAcuseAbierto = false;
        $this->acuseOriginalId = null;
        $this->motivoSustitucion = '';
        $this->nuevoAcuse = null;
        $this->resetValidation();
    }

    public function guardarAcuse(ExpedienteService $expedienteService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();
        Gate::authorize('adjuntarDocumento', $this->servicio);

        if ($this->nuevoAcuse === null) {
            $this->addError('nuevoAcuse', 'Selecciona el archivo del acuse.');

            return;
        }

        $acuseOriginal = $this->acuseOriginalId !== null
            ? ServicioAcuse::findOrFail($this->acuseOriginalId)
            : null;

        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            $expedienteService->registrarAcuse(
                $this->servicio,
                $this->nuevoAcuse,
                $usuario,
                $acuseOriginal,
                $this->motivoSustitucion,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $destino = $campo === 'motivo_sustitucion' ? 'motivoSustitucion' : 'nuevoAcuse';
                foreach ($mensajes as $mensaje) {
                    $this->addError($destino, $mensaje);
                }
            }

            return;
        }

        $fueSustitucion = $acuseOriginal !== null;
        $this->cerrarModalAcuse();
        $this->mensajeExito = $fueSustitucion
            ? 'El acuse fue sustituido conservando el historial.'
            : 'El acuse se registró correctamente.';
    }

    public function render(): View
    {
        Gate::authorize('view', $this->servicio);

        $expediente = Servicio::with([
            'areaSolicitante',
            'estado',
            'usuario',
            'folios.departamento',
            'archivos',
            'acuses.sustituto',
            'acuses.usuarioSustitucion',
        ])->findOrFail($this->servicio->getKey());

        $partidasVisibles = $expediente->partidas()
            ->with(['tipoServicio', 'departamento', 'estado', 'folio'])
            ->orderBy('numero_partida')
            ->get()
            ->filter(fn (Partida $partida): bool => Gate::allows('view', $partida))
            ->values();

        $tiposServicio = TipoServicio::where('activo', true)->orderBy('descripcion')->get();
        $tipoSeleccionado = is_numeric($this->partidaForm['tipo_servicio_id'])
            ? $tiposServicio->firstWhere('id', (int) $this->partidaForm['tipo_servicio_id'])
            : null;

        $partidasDevueltasCount = $expediente->partidas()
            ->whereHas('estado', fn ($q) => $q->where('clave', 'DEVUELTO_AREA'))
            ->count();

        $acusesVigentes = $expediente->acuses->filter(fn (ServicioAcuse $a): bool => $a->sustituto === null)->values();
        $acusesHistoricos = $expediente->acuses->filter(fn (ServicioAcuse $a): bool => $a->sustituto !== null)->values();

        $areasDisponibles = $this->modalEditarExpedienteAbierto
            ? Area::where('activo', true)
                ->orWhere('id', $expediente->area_solicitante_id)
                ->orderBy('nombre')
                ->get()
            : collect();

        return view('livewire.expedientes.detalle-expediente', [
            'expediente' => $expediente,
            'partidas' => $partidasVisibles,
            'partidasDevueltasCount' => $partidasDevueltasCount,
            'acusesVigentes' => $acusesVigentes,
            'acusesHistoricos' => $acusesHistoricos,
            'areasDisponibles' => $areasDisponibles,
            'tiposServicio' => $tiposServicio,
            'departamentos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
            'requiereCantidad' => $tipoSeleccionado?->requiere_cantidad ?? false,
            'puedeEditarExpediente' => Gate::allows('update', $expediente),
            'puedeAgregarPartida' => Gate::allows('create', [Partida::class, $expediente]),
            'puedeAdjuntar' => Gate::allows('adjuntarDocumento', $expediente),
        ]);
    }

    private function limpiarMensajes(): void
    {
        $this->mensajeExito = null;
        $this->mensajeError = null;
    }
}
