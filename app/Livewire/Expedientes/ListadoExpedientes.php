<?php

namespace App\Livewire\Expedientes;

use App\Models\Area;
use App\Models\Estado;
use App\Models\Servicio;
use App\Models\User;
use App\Services\ExpedienteService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ListadoExpedientes extends Component
{
    use WithPagination;

    public string $buscar = '';

    public string $ejercicioFiltro = '';

    public string $estadoFiltro = '';

    public string $areaFiltro = '';

    public string $viaRapidaFiltro = '';

    public function mount(): void
    {
        $this->autorizarListado();
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedEjercicioFiltro(): void
    {
        $this->resetPage();
    }

    public function updatedEstadoFiltro(): void
    {
        $this->resetPage();
    }

    public function updatedAreaFiltro(): void
    {
        $this->resetPage();
    }

    public function updatedViaRapidaFiltro(): void
    {
        $this->resetPage();
    }

    public function render(ExpedienteService $expedienteService): View
    {
        $usuario = $this->autorizarListado();
        $busqueda = trim($this->buscar);

        $expedientes = $expedienteService->queryVisiblesPara($usuario)
            ->with(['areaSolicitante', 'estado', 'usuario', 'folios.departamento'])
            ->withCount(['partidas', 'archivos', 'acuses'])
            ->when($busqueda !== '', function (Builder $q) use ($busqueda): void {
                $q->where(function (Builder $sub) use ($busqueda): void {
                    $sub->where('numero_oficio', 'like', "%{$busqueda}%")
                        ->orWhere('asunto', 'like', "%{$busqueda}%")
                        ->orWhere('nombre_solicitante', 'like', "%{$busqueda}%")
                        ->orWhereHas('folios', fn (Builder $f) => $f->where('folio', 'like', "%{$busqueda}%"));
                });
            })
            ->when($this->ejercicioFiltro !== '', fn (Builder $q) => $q->where('ejercicio', (int) $this->ejercicioFiltro))
            ->when($this->estadoFiltro !== '', fn (Builder $q) => $q->where('estado_id', (int) $this->estadoFiltro))
            ->when($this->areaFiltro !== '', fn (Builder $q) => $q->where('area_solicitante_id', (int) $this->areaFiltro))
            ->when($this->viaRapidaFiltro === '1', fn (Builder $q) => $q->where('es_via_rapida', true))
            ->when($this->viaRapidaFiltro === '0', fn (Builder $q) => $q->where('es_via_rapida', false))
            ->orderByDesc('fecha_recepcion')
            ->orderByDesc('id')
            ->paginate(12);

        $ejercicios = $expedienteService->queryVisiblesPara($usuario)
            ->select('ejercicio')
            ->distinct()
            ->orderByDesc('ejercicio')
            ->pluck('ejercicio');

        return view('livewire.expedientes.listado-expedientes', [
            'expedientes' => $expedientes,
            'ejercicios' => $ejercicios,
            'estados' => Estado::orderBy('id')->get(),
            'areas' => Area::orderBy('nombre')->get(),
            'puedeCrear' => Gate::allows('create', Servicio::class),
        ]);
    }

    private function autorizarListado(): User
    {
        /** @var User|null $usuario */
        $usuario = Auth::user();
        abort_unless(
            $usuario !== null && $usuario->activo && $usuario->checkPermissionTo('servicios.ver', 'web'),
            403
        );

        return $usuario;
    }
}
