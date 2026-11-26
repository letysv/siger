<div>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Gestión de usuarios') }}
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Administración de cuentas de acceso, roles institucionales y departamento de adscripción.
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    {{-- Indicadores rápidos --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total de cuentas</p>
            <p class="mt-1 text-2xl font-extrabold text-siger-900">{{ number_format($resumen['total']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cuentas activas</p>
            <p class="mt-1 text-2xl font-extrabold text-emerald-700">{{ number_format($resumen['activos']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cuentas inactivas</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-600">{{ number_format($resumen['inactivos']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sin adscripción</p>
            <p class="mt-1 text-2xl font-extrabold text-siger-700">{{ number_format($resumen['sin_departamento']) }}</p>
        </div>
    </div>

    {{-- Mensajes de retroalimentación --}}
    @if ($mensajeExito)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center justify-between gap-3">
            <span>{{ $mensajeExito }}</span>
            <button type="button" wire:click="$set('mensajeExito', null)" class="text-emerald-600 hover:text-emerald-800 font-semibold text-xs">
                Cerrar
            </button>
        </div>
    @endif

    @if ($mensajeError)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 flex items-center justify-between gap-3">
            <span>{{ $mensajeError }}</span>
            <button type="button" wire:click="$set('mensajeError', null)" class="text-red-600 hover:text-red-800 font-semibold text-xs">
                Cerrar
            </button>
        </div>
    @endif

    {{-- Listado y filtros --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200">
        <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 flex-1">
                <x-input type="search"
                         wire:model.live.debounce.300ms="buscar"
                         placeholder="Buscar por nombre, usuario o correo..."
                         class="w-full text-sm" />

                <select wire:model.live="departamentoFiltro"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                    <option value="">Todos los departamentos</option>
                    <option value="sin_departamento">Sin departamento (alcance general)</option>
                    @foreach ($departamentos as $depto)
                        <option value="{{ $depto->id }}">{{ $depto->siglas }} — {{ $depto->nombre }}</option>
                    @endforeach
                </select>

                <select wire:model.live="rolFiltro"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                    <option value="">Todos los roles</option>
                    @foreach ($rolesDisponibles as $nombreRol)
                        <option value="{{ $nombreRol }}">{{ $nombreRol }}</option>
                    @endforeach
                </select>

                <select wire:model.live="estadoFiltro"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                    <option value="todos">Todos los estados</option>
                    <option value="activos">Solo activos</option>
                    <option value="inactivos">Solo inactivos</option>
                </select>
            </div>

            <div class="flex items-center justify-end">
                <button type="button"
                        wire:click="abrirCrear"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-siger-700 hover:bg-siger-800 text-white text-xs font-semibold uppercase tracking-wider rounded-lg shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Nuevo usuario</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Usuario</th>
                        <th class="px-6 py-3">Departamento de adscripción</th>
                        <th class="px-6 py-3">Roles asignados</th>
                        <th class="px-6 py-3">Operativo vinculado</th>
                        <th class="px-6 py-3">Estado</th>
                        <th class="px-6 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($usuarios as $usuario)
                        <tr wire:key="usuario-{{ $usuario->id }}" class="hover:bg-slate-50/70">
                            <td class="px-6 py-3.5">
                                <div class="font-medium text-slate-800">
                                    {{ $usuario->name }}
                                    @if ((int) $usuario->id === (int) Auth::id())
                                        <span class="ms-1 text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-siger-100 text-siger-700">Tú</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500">
                                    @if ($usuario->username)
                                        <span class="font-mono font-semibold text-siger-700">{{ $usuario->username }}</span>
                                    @endif
                                    @if ($usuario->username && $usuario->email)
                                        <span class="mx-1">·</span>
                                    @endif
                                    @if ($usuario->email)
                                        <span>{{ $usuario->email }}</span>
                                    @elseif (! $usuario->username)
                                        <span>Sin correo</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-3.5 text-slate-600">
                                @if ($usuario->departamento)
                                    <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">{{ $usuario->departamento->siglas }}</span>
                                    <span class="ms-1 text-xs text-slate-600">{{ $usuario->departamento->nombre }}</span>
                                @else
                                    <span class="text-xs text-slate-400">Sin adscripción</span>
                                @endif
                            </td>
                            <td class="px-6 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($usuario->roles as $rol)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700">
                                            {{ $rol->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-slate-400">Sin roles</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-6 py-3.5 text-xs text-slate-600">
                                @if ($usuario->operativo)
                                    <span class="font-medium text-slate-700">{{ $usuario->operativo->nombre }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $usuario->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $usuario->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-right whitespace-nowrap space-x-2">
                                <button type="button" wire:click="abrirEditar({{ $usuario->id }})" class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                                    Editar
                                </button>
                                <button type="button" wire:click="abrirRestablecerContrasena({{ $usuario->id }})" class="text-xs font-semibold text-indigo-700 hover:text-indigo-900">
                                    Contraseña
                                </button>
                                @if ((int) $usuario->id !== (int) Auth::id())
                                    <button type="button" wire:click="alternarEstado({{ $usuario->id }})" class="text-xs font-semibold {{ $usuario->activo ? 'text-amber-700 hover:text-amber-900' : 'text-emerald-700 hover:text-emerald-900' }}">
                                        {{ $usuario->activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                No se encontraron usuarios con los criterios seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($usuarios->hasPages())
            <div class="px-6 py-4 border-t border-slate-100">
                {{ $usuarios->links() }}
            </div>
        @endif
    </div>

    {{-- Modal de creación / edición de usuario --}}
    <x-dialog-modal wire:model.live="modalUsuarioAbierto">
        <x-slot name="title">
            {{ $usuarioEditandoId !== null ? 'Editar usuario' : 'Nuevo usuario' }}
        </x-slot>

        <x-slot name="content">
            <form wire:submit="guardar" id="form-usuario" class="space-y-4">
                <div>
                    <x-label for="usuario_nombre" value="Nombre completo *" />
                    <x-input id="usuario_nombre" type="text" class="mt-1 block w-full" wire:model="form.name" maxlength="255" />
                    <x-input-error for="form.name" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-label for="usuario_username" value="Usuario de acceso *" />
                        <x-input id="usuario_username" type="text" class="mt-1 block w-full lowercase font-mono" wire:model="form.username" maxlength="50" placeholder="ej. agonzalez" />
                        <x-input-error for="form.username" class="mt-1" />
                    </div>

                    <div>
                        <x-label for="usuario_email" value="Correo electrónico (opcional)" />
                        <x-input id="usuario_email" type="email" class="mt-1 block w-full" wire:model="form.email" maxlength="255" />
                        <x-input-error for="form.email" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-label for="usuario_departamento" value="Departamento de adscripción" />
                    <select id="usuario_departamento" wire:model="form.departamento_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">Sin departamento (solo roles administrativos generales)</option>
                        @foreach ($departamentos as $depto)
                            <option value="{{ $depto->id }}">{{ $depto->siglas }} — {{ $depto->nombre }}{{ $depto->activo ? '' : ' (Inactivo)' }}</option>
                        @endforeach
                    </select>
                    <x-input-error for="form.departamento_id" class="mt-1" />
                </div>

                <div>
                    <x-label value="Roles asignados *" />
                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach ($rolesDisponibles as $nombreRol)
                            <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 cursor-pointer">
                                <input type="checkbox"
                                       value="{{ $nombreRol }}"
                                       wire:model="form.roles"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span>{{ $nombreRol }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error for="form.roles" class="mt-1" />
                    <x-input-error for="form.roles.*" class="mt-1" />
                </div>

                @if ($usuarioEditandoId === null)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                        <div>
                            <x-label for="usuario_password" value="Contraseña inicial *" />
                            <x-input id="usuario_password" type="password" class="mt-1 block w-full" wire:model="form.password" autocomplete="new-password" />
                            <x-input-error for="form.password" class="mt-1" />
                        </div>
                        <div>
                            <x-label for="usuario_password_confirmation" value="Confirmar contraseña *" />
                            <x-input id="usuario_password_confirmation" type="password" class="mt-1 block w-full" wire:model="form.password_confirmation" autocomplete="new-password" />
                        </div>
                    </div>
                @endif

                <div class="pt-2 border-t border-slate-100">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <x-checkbox wire:model="form.activo" :disabled="$usuarioEditandoId !== null && (int) $usuarioEditandoId === (int) Auth::id()" />
                        <span>Cuenta activa</span>
                    </label>
                    <x-input-error for="form.activo" class="mt-1" />
                </div>
            </form>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="cerrarModalUsuario" wire:loading.attr="disabled">
                Cancelar
            </x-secondary-button>

            <x-button class="ms-3" type="submit" form="form-usuario" wire:loading.attr="disabled">
                Guardar
            </x-button>
        </x-slot>
    </x-dialog-modal>

    {{-- Modal de restablecimiento de contraseña --}}
    <x-dialog-modal wire:model.live="modalContrasenaAbierto" maxWidth="md">
        <x-slot name="title">
            Restablecer contraseña — {{ $usuarioContrasenaNombre }}
        </x-slot>

        <x-slot name="content">
            <form wire:submit="guardarContrasena" id="form-password-usuario" class="space-y-4">
                <p class="text-xs text-slate-500">
                    Al restablecer la contraseña se cerrarán las sesiones activas de esta cuenta.
                </p>

                <div>
                    <x-label for="reset_password" value="Nueva contraseña *" />
                    <x-input id="reset_password" type="password" class="mt-1 block w-full" wire:model="passwordForm.password" autocomplete="new-password" />
                    <x-input-error for="passwordForm.password" class="mt-1" />
                </div>

                <div>
                    <x-label for="reset_password_confirmation" value="Confirmar nueva contraseña *" />
                    <x-input id="reset_password_confirmation" type="password" class="mt-1 block w-full" wire:model="passwordForm.password_confirmation" autocomplete="new-password" />
                </div>
            </form>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="cerrarModalContrasena" wire:loading.attr="disabled">
                Cancelar
            </x-secondary-button>

            <x-button class="ms-3" type="submit" form="form-password-usuario" wire:loading.attr="disabled">
                Actualizar contraseña
            </x-button>
        </x-slot>
    </x-dialog-modal>
        </div>
    </div>
</div>
