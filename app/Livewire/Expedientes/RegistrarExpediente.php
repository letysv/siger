<?php

namespace App\Livewire\Expedientes;

use App\Models\Area;
use App\Models\Departamento;
use App\Models\Servicio;
use App\Models\TipoServicio;
use App\Models\User;
use App\Services\ExpedienteService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class RegistrarExpediente extends Component
{
    use WithFileUploads;

    /** @var array<string, mixed> */
    public array $form = [
        'numero_oficio' => '',
        'fecha_oficio' => '',
        'fecha_recepcion' => '',
        'area_solicitante_id' => '',
        'nombre_solicitante' => '',
        'dirigido_a' => '',
        'asunto' => '',
        'descripcion' => '',
        'es_via_rapida' => false,
        'tipo_servicio_id' => '',
        'concepto' => '',
        'cantidad_solicitada' => '',
        'unidad_medida' => '',
        'departamento_id' => '',
        'observaciones' => '',
    ];

    /** @var array<int, mixed> */
    public array $archivos = [];

    public mixed $acuse = null;

    public function mount(): void
    {
        Gate::authorize('create', Servicio::class);

        $this->form['fecha_oficio'] = now()->toDateString();
        $this->form['fecha_recepcion'] = now()->format('Y-m-d\TH:i');
    }

    public function updatedFormEsViaRapida(bool $valor): void
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        if ($valor
            && ! $usuario->hasAnyRole(['Super usuario', 'Administrador'])
            && $usuario->hasRole('Recepción/Coordinador')
            && $usuario->departamento_id !== null) {
            $this->form['departamento_id'] = (string) $usuario->departamento_id;
        }
    }

    public function guardar(ExpedienteService $expedienteService): mixed
    {
        Gate::authorize('create', Servicio::class);
        $this->resetValidation();

        /** @var User $usuario */
        $usuario = Auth::user();

        try {
            $servicio = $expedienteService->registrar(
                $this->form,
                $usuario,
                $this->archivos,
                $this->acuse,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $prefijo = str_starts_with($campo, 'archivos') || $campo === 'acuse'
                    ? $campo
                    : "form.{$campo}";

                foreach ($mensajes as $mensaje) {
                    $this->addError($prefijo, $mensaje);
                }
            }

            return null;
        }

        session()->flash('expediente_status', "Expediente #{$servicio->id} registrado correctamente con su partida inicial (#1).");

        if (Gate::allows('view', $servicio)) {
            return $this->redirectRoute('expedientes.show', $servicio);
        }

        return $this->redirectRoute('expedientes.index');
    }

    public function render(ExpedienteService $expedienteService): View
    {
        Gate::authorize('create', Servicio::class);

        /** @var User $usuario */
        $usuario = Auth::user();
        $esGeneral = $usuario->hasAnyRole(['Super usuario', 'Administrador']);

        $ejercicio = null;
        if (! empty($this->form['fecha_recepcion'])) {
            try {
                $ejercicio = (int) Carbon::parse((string) $this->form['fecha_recepcion'])->year;
            } catch (\Throwable) {
                $ejercicio = null;
            }
        }

        $areaId = is_numeric($this->form['area_solicitante_id']) ? (int) $this->form['area_solicitante_id'] : null;
        $posiblesDuplicados = $expedienteService->buscarPosiblesDuplicados(
            $areaId,
            $ejercicio,
            (string) ($this->form['numero_oficio'] ?? ''),
        );

        $tiposServicio = TipoServicio::where('activo', true)->orderBy('descripcion')->get();
        $tipoSeleccionado = is_numeric($this->form['tipo_servicio_id'])
            ? $tiposServicio->firstWhere('id', (int) $this->form['tipo_servicio_id'])
            : null;

        return view('livewire.expedientes.registrar-expediente', [
            'areas' => Area::where('activo', true)->orderBy('nombre')->get(),
            'departamentos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
            'tiposServicio' => $tiposServicio,
            'requiereCantidad' => $tipoSeleccionado?->requiere_cantidad ?? false,
            'posiblesDuplicados' => $posiblesDuplicados,
            'esGeneral' => $esGeneral,
            'departamentoUsuarioId' => $usuario->departamento_id,
        ]);
    }
}
