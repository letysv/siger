<?php

namespace App\Livewire\Usuarios;

use App\Models\Departamento;
use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
class GestionUsuarios extends Component
{
    use WithPagination;

    public string $buscar = '';

    public string $departamentoFiltro = '';

    public string $rolFiltro = '';

    public string $estadoFiltro = 'todos';

    public bool $modalUsuarioAbierto = false;

    public bool $modalContrasenaAbierto = false;

    public ?int $usuarioEditandoId = null;

    public ?int $usuarioContrasenaId = null;

    public string $usuarioContrasenaNombre = '';

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var array<string, string> */
    public array $passwordForm = [
        'password' => '',
        'password_confirmation' => '',
    ];

    public ?string $mensajeExito = null;

    public ?string $mensajeError = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
        $this->reiniciarFormulario();
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedDepartamentoFiltro(): void
    {
        $this->resetPage();
    }

    public function updatedRolFiltro(): void
    {
        $this->resetPage();
    }

    public function updatedEstadoFiltro(): void
    {
        $this->resetPage();
    }

    public function abrirCrear(): void
    {
        Gate::authorize('create', User::class);

        $this->limpiarMensajes();
        $this->resetValidation();
        $this->usuarioEditandoId = null;
        $this->reiniciarFormulario();
        $this->modalUsuarioAbierto = true;
    }

    public function abrirEditar(int $id): void
    {
        $usuario = User::with('roles')->findOrFail($id);
        Gate::authorize('update', $usuario);

        $this->limpiarMensajes();
        $this->resetValidation();
        $this->usuarioEditandoId = $usuario->getKey();
        $this->form = [
            'name' => $usuario->name,
            'username' => $usuario->username ?? '',
            'email' => $usuario->email ?? '',
            'departamento_id' => $usuario->departamento_id !== null ? (string) $usuario->departamento_id : '',
            'activo' => $usuario->activo,
            'roles' => $usuario->getRoleNames()->values()->all(),
            'password' => '',
            'password_confirmation' => '',
        ];
        $this->modalUsuarioAbierto = true;
    }

    public function cerrarModalUsuario(): void
    {
        $this->modalUsuarioAbierto = false;
        $this->usuarioEditandoId = null;
        $this->resetValidation();
        $this->reiniciarFormulario();
    }

    public function guardar(UsuarioService $usuarioService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        /** @var User $actor */
        $actor = Auth::user();
        $editando = $this->usuarioEditandoId !== null
            ? User::with(['roles', 'operativo'])->findOrFail($this->usuarioEditandoId)
            : null;

        if ($editando !== null) {
            Gate::authorize('update', $editando);
        } else {
            Gate::authorize('create', User::class);
        }

        try {
            if ($editando !== null) {
                $usuarioService->actualizar($editando, $this->form, $actor);
            } else {
                $usuarioService->crear($this->form, $actor);
            }
        } catch (ValidationException $e) {
            $this->asignarErrores($e, 'form');

            return;
        }

        $accion = $editando !== null ? 'actualizado' : 'registrado';
        $this->cerrarModalUsuario();
        $this->mensajeExito = "El usuario se ha {$accion} correctamente.";
    }

    public function abrirRestablecerContrasena(int $id): void
    {
        $usuario = User::findOrFail($id);
        Gate::authorize('resetPassword', $usuario);

        $this->limpiarMensajes();
        $this->resetValidation();
        $this->usuarioContrasenaId = $usuario->getKey();
        $this->usuarioContrasenaNombre = $usuario->name;
        $this->passwordForm = [
            'password' => '',
            'password_confirmation' => '',
        ];
        $this->modalContrasenaAbierto = true;
    }

    public function cerrarModalContrasena(): void
    {
        $this->modalContrasenaAbierto = false;
        $this->usuarioContrasenaId = null;
        $this->usuarioContrasenaNombre = '';
        $this->passwordForm = [
            'password' => '',
            'password_confirmation' => '',
        ];
        $this->resetValidation();
    }

    public function guardarContrasena(UsuarioService $usuarioService): void
    {
        $this->limpiarMensajes();
        $this->resetValidation();

        $usuario = User::findOrFail((int) $this->usuarioContrasenaId);
        Gate::authorize('resetPassword', $usuario);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $usuarioService->restablecerContrasena($usuario, $this->passwordForm, $actor);
        } catch (ValidationException $e) {
            $this->asignarErrores($e, 'passwordForm');

            return;
        }

        $nombre = $usuario->name;
        $this->cerrarModalContrasena();
        $this->mensajeExito = "Se restableció la contraseña de {$nombre}.";
    }

    public function alternarEstado(int $id, UsuarioService $usuarioService): void
    {
        $this->limpiarMensajes();

        $usuario = User::findOrFail($id);
        Gate::authorize('toggleStatus', $usuario);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $actualizado = $usuarioService->alternarEstado($usuario, $actor);
        } catch (ValidationException $e) {
            $this->mensajeError = collect($e->errors())->flatten()->first();

            return;
        }

        $this->mensajeExito = $actualizado->activo
            ? "La cuenta de {$actualizado->name} fue activada."
            : "La cuenta de {$actualizado->name} fue desactivada y sus sesiones activas fueron cerradas.";
    }

    public function render(): View
    {
        Gate::authorize('viewAny', User::class);

        $busqueda = trim($this->buscar);

        $usuarios = User::query()
            ->with(['departamento', 'roles', 'operativo'])
            ->when($busqueda !== '', function (Builder $q) use ($busqueda): void {
                $q->where(function (Builder $sub) use ($busqueda): void {
                    $sub->where('name', 'like', "%{$busqueda}%")
                        ->orWhere('username', 'like', "%{$busqueda}%")
                        ->orWhere('email', 'like', "%{$busqueda}%");
                });
            })
            ->when($this->departamentoFiltro === 'sin_departamento', function (Builder $q): void {
                $q->whereNull('departamento_id');
            })
            ->when(is_numeric($this->departamentoFiltro), function (Builder $q): void {
                $q->where('departamento_id', (int) $this->departamentoFiltro);
            })
            ->when($this->rolFiltro !== '', function (Builder $q): void {
                $q->whereHas('roles', fn (Builder $r) => $r->where('name', $this->rolFiltro));
            })
            ->when($this->estadoFiltro === 'activos', fn (Builder $q) => $q->where('activo', true))
            ->when($this->estadoFiltro === 'inactivos', fn (Builder $q) => $q->where('activo', false))
            ->orderBy('name')
            ->paginate(12);

        $departamentos = Departamento::query()
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name');

        return view('livewire.usuarios.gestion-usuarios', [
            'usuarios' => $usuarios,
            'departamentos' => $departamentos,
            'rolesDisponibles' => $roles,
            'resumen' => [
                'total' => User::count(),
                'activos' => User::where('activo', true)->count(),
                'inactivos' => User::where('activo', false)->count(),
                'sin_departamento' => User::whereNull('departamento_id')->count(),
            ],
        ]);
    }

    private function reiniciarFormulario(): void
    {
        $this->form = [
            'name' => '',
            'username' => '',
            'email' => '',
            'departamento_id' => '',
            'activo' => true,
            'roles' => [],
            'password' => '',
            'password_confirmation' => '',
        ];
    }

    private function asignarErrores(ValidationException $exception, string $prefijo): void
    {
        foreach ($exception->errors() as $campo => $mensajes) {
            foreach ($mensajes as $mensaje) {
                $this->addError("{$prefijo}.{$campo}", $mensaje);
            }
        }
    }

    private function limpiarMensajes(): void
    {
        $this->mensajeExito = null;
        $this->mensajeError = null;
    }
}
