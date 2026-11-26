<?php

namespace App\Livewire\Catalogos;

use App\Models\Area;
use App\Models\Departamento;
use App\Models\Operativo;
use App\Models\TipoServicio;
use App\Models\User;
use App\Services\CatalogoService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class GestionCatalogos extends Component
{
    use WithPagination;

    public const TABS = ['areas', 'departamentos', 'tipos_servicio', 'operativos'];

    #[Url(as: 'tab', except: 'areas')]
    public string $tab = 'areas';

    public string $buscar = '';

    public string $estadoFiltro = 'todos';

    public string $departamentoFiltro = '';

    public bool $modalAbierto = false;

    public ?int $registroEditandoId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public ?string $mensajeExito = null;

    public ?string $mensajeError = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Area::class);

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'areas';
        }

        $this->reiniciarFormulario();
    }

    public function cambiarTab(string $nuevoTab): void
    {
        if (! in_array($nuevoTab, self::TABS, true)) {
            return;
        }

        $this->tab = $nuevoTab;
        $this->buscar = '';
        $this->estadoFiltro = 'todos';
        $this->departamentoFiltro = '';
        $this->cerrarModal();
        $this->limpiarMensajes();
        $this->resetPage();
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

    public function abrirCrear(): void
    {
        Gate::authorize('create', $this->claseModeloActual());

        $this->limpiarMensajes();
        $this->resetValidation();
        $this->registroEditandoId = null;
        $this->reiniciarFormulario();
        $this->modalAbierto = true;
    }

    public function abrirEditar(int $id): void
    {
        $registro = $this->encontrarRegistro($id);
        Gate::authorize('update', $registro);

        $this->limpiarMensajes();
        $this->resetValidation();
        $this->registroEditandoId = $registro->getKey();
        $this->cargarFormulario($registro);
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->registroEditandoId = null;
        $this->resetValidation();
        $this->reiniciarFormulario();
    }

    public function guardar(CatalogoService $catalogoService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $registro = $this->registroEditandoId !== null
            ? $this->encontrarRegistro($this->registroEditandoId)
            : null;

        if ($registro !== null) {
            Gate::authorize('update', $registro);
        } else {
            Gate::authorize('create', $this->claseModeloActual());
        }

        try {
            match ($this->tab) {
                'areas' => $catalogoService->guardarArea($this->form, $registro instanceof Area ? $registro : null),
                'departamentos' => $catalogoService->guardarDepartamento($this->form, $registro instanceof Departamento ? $registro : null),
                'tipos_servicio' => $catalogoService->guardarTipoServicio($this->form, $registro instanceof TipoServicio ? $registro : null),
                'operativos' => $catalogoService->guardarOperativo($this->form, $registro instanceof Operativo ? $registro : null),
            };
        } catch (ValidationException $e) {
            $this->asignarErroresFormulario($e);

            return;
        }

        $accion = $registro !== null ? 'actualizado' : 'registrado';
        $this->cerrarModal();
        $this->mensajeExito = "El registro se ha {$accion} correctamente.";
    }

    public function alternarEstado(int $id, CatalogoService $catalogoService): void
    {
        $this->limpiarMensajes();

        /** @var Area|Departamento|TipoServicio|Operativo $registro */
        $registro = $this->encontrarRegistro($id);
        Gate::authorize('toggleStatus', $registro);

        try {
            $actualizado = $catalogoService->alternarEstado($registro);
        } catch (ValidationException $e) {
            $this->mensajeError = collect($e->errors())->flatten()->first();

            return;
        }

        $this->mensajeExito = $actualizado->activo
            ? 'El registro fue activado correctamente.'
            : 'El registro fue desactivado correctamente.';
    }

    public function render(): View
    {
        Gate::authorize('viewAny', $this->claseModeloActual());

        $departamentos = Departamento::query()
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        $usuariosDisponibles = $this->tab === 'operativos'
            ? $this->consultarUsuariosDisponibles()
            : collect();

        return view('livewire.catalogos.gestion-catalogos', [
            'registros' => $this->consultarRegistros(),
            'departamentos' => $departamentos,
            'usuariosDisponibles' => $usuariosDisponibles,
            'conteos' => [
                'areas' => Area::count(),
                'departamentos' => Departamento::count(),
                'tipos_servicio' => TipoServicio::count(),
                'operativos' => Operativo::count(),
            ],
            'departamentoProtegido' => $this->tab === 'departamentos'
                && $this->registroEditandoId !== null
                && Departamento::find($this->registroEditandoId)?->esProtegido() === true,
        ]);
    }

    private function consultarRegistros()
    {
        $busqueda = trim($this->buscar);

        $query = match ($this->tab) {
            'areas' => Area::query()
                ->withCount('servicios')
                ->when($busqueda !== '', function (Builder $q) use ($busqueda): void {
                    $q->where(function (Builder $sub) use ($busqueda): void {
                        $sub->where('clave', 'like', "%{$busqueda}%")
                            ->orWhere('nombre', 'like', "%{$busqueda}%")
                            ->orWhere('titular', 'like', "%{$busqueda}%")
                            ->orWhere('tipo_area', 'like', "%{$busqueda}%");
                    });
                })
                ->orderBy('nombre'),

            'departamentos' => Departamento::query()
                ->withCount(['usuarios', 'operativos', 'partidas'])
                ->when($busqueda !== '', function (Builder $q) use ($busqueda): void {
                    $q->where(function (Builder $sub) use ($busqueda): void {
                        $sub->where('siglas', 'like', "%{$busqueda}%")
                            ->orWhere('nombre', 'like', "%{$busqueda}%")
                            ->orWhere('titular_responsable', 'like', "%{$busqueda}%");
                    });
                })
                ->orderBy('nombre'),

            'tipos_servicio' => TipoServicio::query()
                ->withCount('partidas')
                ->when($busqueda !== '', function (Builder $q) use ($busqueda): void {
                    $q->where('descripcion', 'like', "%{$busqueda}%");
                })
                ->orderBy('descripcion'),

            'operativos' => Operativo::query()
                ->with(['departamento', 'usuario'])
                ->withCount('asignaciones')
                ->when($busqueda !== '', function (Builder $q) use ($busqueda): void {
                    $q->where(function (Builder $sub) use ($busqueda): void {
                        $sub->where('nombre', 'like', "%{$busqueda}%")
                            ->orWhereHas('usuario', fn (Builder $u) => $u->where('name', 'like', "%{$busqueda}%")
                                ->orWhere('username', 'like', "%{$busqueda}%")
                                ->orWhere('email', 'like', "%{$busqueda}%"));
                    });
                })
                ->when($this->departamentoFiltro !== '', function (Builder $q): void {
                    $q->where('departamento_id', (int) $this->departamentoFiltro);
                })
                ->orderBy('nombre'),
        };

        if ($this->estadoFiltro === 'activos') {
            $query->where('activo', true);
        } elseif ($this->estadoFiltro === 'inactivos') {
            $query->where('activo', false);
        }

        return $query->paginate(10);
    }

    private function consultarUsuariosDisponibles()
    {
        $operativoId = $this->registroEditandoId;
        $departamentoSeleccionado = $this->form['departamento_id'] ?? null;

        return User::query()
            ->with('departamento')
            ->where(function (Builder $q) use ($operativoId): void {
                $q->where('activo', true)
                    ->whereDoesntHave('operativo');

                if ($operativoId !== null) {
                    $q->orWhereHas('operativo', fn (Builder $op) => $op->where('id', $operativoId));
                }
            })
            ->when($departamentoSeleccionado !== '' && $departamentoSeleccionado !== null, function (Builder $q) use ($departamentoSeleccionado, $operativoId): void {
                $q->where(function (Builder $sub) use ($departamentoSeleccionado, $operativoId): void {
                    $sub->whereNull('departamento_id')
                        ->orWhere('departamento_id', (int) $departamentoSeleccionado);

                    if ($operativoId !== null) {
                        $sub->orWhereHas('operativo', fn (Builder $op) => $op->where('id', $operativoId));
                    }
                });
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @return class-string<Model>
     */
    private function claseModeloActual(): string
    {
        return match ($this->tab) {
            'areas' => Area::class,
            'departamentos' => Departamento::class,
            'tipos_servicio' => TipoServicio::class,
            'operativos' => Operativo::class,
        };
    }

    private function encontrarRegistro(int $id): Model
    {
        $modelo = $this->claseModeloActual();

        return $modelo::findOrFail($id);
    }

    private function reiniciarFormulario(): void
    {
        $this->form = match ($this->tab) {
            'areas' => [
                'clave' => '',
                'nombre' => '',
                'titular' => '',
                'tipo_area' => '',
                'activo' => true,
            ],
            'departamentos' => [
                'nombre' => '',
                'siglas' => '',
                'titular_responsable' => '',
                'ubicacion_oficina' => '',
                'activo' => true,
            ],
            'tipos_servicio' => [
                'descripcion' => '',
                'requiere_cantidad' => false,
                'activo' => true,
            ],
            'operativos' => [
                'nombre' => '',
                'departamento_id' => '',
                'user_id' => '',
                'activo' => true,
            ],
        };
    }

    private function cargarFormulario(Model $registro): void
    {
        $this->form = match (true) {
            $registro instanceof Area => [
                'clave' => $registro->clave,
                'nombre' => $registro->nombre,
                'titular' => $registro->titular ?? '',
                'tipo_area' => $registro->tipo_area,
                'activo' => $registro->activo,
            ],
            $registro instanceof Departamento => [
                'nombre' => $registro->nombre,
                'siglas' => $registro->siglas,
                'titular_responsable' => $registro->titular_responsable ?? '',
                'ubicacion_oficina' => $registro->ubicacion_oficina ?? '',
                'activo' => $registro->activo,
            ],
            $registro instanceof TipoServicio => [
                'descripcion' => $registro->descripcion,
                'requiere_cantidad' => $registro->requiere_cantidad,
                'activo' => $registro->activo,
            ],
            $registro instanceof Operativo => [
                'nombre' => $registro->nombre,
                'departamento_id' => (string) $registro->departamento_id,
                'user_id' => $registro->user_id !== null ? (string) $registro->user_id : '',
                'activo' => $registro->activo,
            ],
        };
    }

    private function asignarErroresFormulario(ValidationException $exception): void
    {
        foreach ($exception->errors() as $campo => $mensajes) {
            foreach ($mensajes as $mensaje) {
                $this->addError("form.{$campo}", $mensaje);
            }
        }
    }

    private function limpiarMensajes(): void
    {
        $this->mensajeExito = null;
        $this->mensajeError = null;
    }
}
