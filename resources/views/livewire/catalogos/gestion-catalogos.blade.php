<div>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Catálogos del sistema') }}
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Administración de áreas solicitantes, departamentos, tipos de servicio y personal operativo.
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
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

    {{-- Navegación por pestañas --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200">
        <div class="border-b border-slate-200 px-4 sm:px-6">
            <nav class="-mb-px flex flex-wrap gap-6" aria-label="Catálogos">
                @php
                    $pestanas = [
                        'areas' => 'Áreas solicitantes',
                        'departamentos' => 'Departamentos',
                        'tipos_servicio' => 'Tipos de servicio',
                        'operativos' => 'Personal operativo',
                    ];
                @endphp

                @foreach ($pestanas as $claveTab => $tituloTab)
                    <button type="button"
                            wire:click="cambiarTab('{{ $claveTab }}')"
                            class="py-4 px-1 border-b-2 text-sm font-medium whitespace-nowrap transition-colors flex items-center gap-2 {{ $tab === $claveTab ? 'border-siger-700 text-siger-800 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                        <span>{{ $tituloTab }}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $tab === $claveTab ? 'bg-siger-100 text-siger-800' : 'bg-slate-100 text-slate-600' }}">
                            {{ $conteos[$claveTab] ?? 0 }}
                        </span>
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Barra de filtros y acción principal --}}
        <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex flex-col sm:flex-row gap-3 flex-1">
                <div class="flex-1 max-w-md">
                    <x-input type="search"
                             wire:model.live.debounce.300ms="buscar"
                             placeholder="Buscar en el catálogo actual..."
                             class="w-full text-sm" />
                </div>

                <select wire:model.live="estadoFiltro"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                    <option value="todos">Todos los estados</option>
                    <option value="activos">Solo activos</option>
                    <option value="inactivos">Solo inactivos</option>
                </select>

                @if ($tab === 'operativos')
                    <select wire:model.live="departamentoFiltro"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">Todos los departamentos</option>
                        @foreach ($departamentos as $depto)
                            <option value="{{ $depto->id }}">{{ $depto->siglas }} — {{ $depto->nombre }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="flex items-center justify-end">
                <button type="button"
                        wire:click="abrirCrear"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-siger-700 hover:bg-siger-800 text-white text-xs font-semibold uppercase tracking-wider rounded-lg shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>
                        @switch($tab)
                            @case('areas') Nueva área @break
                            @case('departamentos') Nuevo departamento @break
                            @case('tipos_servicio') Nuevo tipo de servicio @break
                            @case('operativos') Nuevo operativo @break
                        @endswitch
                    </span>
                </button>
            </div>
        </div>

        {{-- Tabla de registros --}}
        <div class="overflow-x-auto">
            @if ($tab === 'areas')
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Clave</th>
                            <th class="px-6 py-3">Nombre</th>
                            <th class="px-6 py-3">Tipo de área</th>
                            <th class="px-6 py-3">Titular</th>
                            <th class="px-6 py-3">Expedientes</th>
                            <th class="px-6 py-3">Estado</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($registros as $area)
                            <tr wire:key="area-{{ $area->id }}" class="hover:bg-slate-50/70">
                                <td class="px-6 py-3.5 font-mono text-xs font-semibold text-siger-800">{{ $area->clave }}</td>
                                <td class="px-6 py-3.5 font-medium text-slate-800">{{ $area->nombre }}</td>
                                <td class="px-6 py-3.5 text-slate-600">{{ $area->tipo_area }}</td>
                                <td class="px-6 py-3.5 text-slate-600">{{ $area->titular ?: '—' }}</td>
                                <td class="px-6 py-3.5 text-slate-600">{{ $area->servicios_count }}</td>
                                <td class="px-6 py-3.5">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $area->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $area->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5 text-right whitespace-nowrap space-x-2">
                                    <button type="button" wire:click="abrirEditar({{ $area->id }})" class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                                        Editar
                                    </button>
                                    <button type="button" wire:click="alternarEstado({{ $area->id }})" class="text-xs font-semibold {{ $area->activo ? 'text-amber-700 hover:text-amber-900' : 'text-emerald-700 hover:text-emerald-900' }}">
                                        {{ $area->activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                                    No se encontraron áreas con los criterios seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif ($tab === 'departamentos')
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Siglas</th>
                            <th class="px-6 py-3">Nombre</th>
                            <th class="px-6 py-3">Titular responsable</th>
                            <th class="px-6 py-3">Ubicación</th>
                            <th class="px-6 py-3">Usuarios / Operativos</th>
                            <th class="px-6 py-3">Estado</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($registros as $depto)
                            <tr wire:key="depto-{{ $depto->id }}" class="hover:bg-slate-50/70">
                                <td class="px-6 py-3.5">
                                    <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-siger-100 text-siger-800">{{ $depto->siglas }}</span>
                                    @if ($depto->esProtegido())
                                        <span class="ms-1 text-[10px] uppercase font-semibold text-slate-400">Base</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3.5 font-medium text-slate-800">{{ $depto->nombre }}</td>
                                <td class="px-6 py-3.5 text-slate-600">{{ $depto->titular_responsable ?: '—' }}</td>
                                <td class="px-6 py-3.5 text-slate-600">{{ $depto->ubicacion_oficina ?: '—' }}</td>
                                <td class="px-6 py-3.5 text-slate-600 text-xs">
                                    {{ $depto->usuarios_count }} usuarios · {{ $depto->operativos_count }} operativos
                                </td>
                                <td class="px-6 py-3.5">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $depto->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $depto->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5 text-right whitespace-nowrap space-x-2">
                                    <button type="button" wire:click="abrirEditar({{ $depto->id }})" class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                                        Editar
                                    </button>
                                    @if (! $depto->esProtegido() || ! $depto->activo)
                                        <button type="button" wire:click="alternarEstado({{ $depto->id }})" class="text-xs font-semibold {{ $depto->activo ? 'text-amber-700 hover:text-amber-900' : 'text-emerald-700 hover:text-emerald-900' }}">
                                            {{ $depto->activo ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                                    No se encontraron departamentos con los criterios seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif ($tab === 'tipos_servicio')
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Descripción</th>
                            <th class="px-6 py-3">Requiere cantidad</th>
                            <th class="px-6 py-3">Partidas</th>
                            <th class="px-6 py-3">Estado</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($registros as $tipo)
                            <tr wire:key="tipo-{{ $tipo->id }}" class="hover:bg-slate-50/70">
                                <td class="px-6 py-3.5 font-medium text-slate-800">{{ $tipo->descripcion }}</td>
                                <td class="px-6 py-3.5 text-slate-600">
                                    {{ $tipo->requiere_cantidad ? 'Sí' : 'No' }}
                                </td>
                                <td class="px-6 py-3.5 text-slate-600">{{ $tipo->partidas_count }}</td>
                                <td class="px-6 py-3.5">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $tipo->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $tipo->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5 text-right whitespace-nowrap space-x-2">
                                    <button type="button" wire:click="abrirEditar({{ $tipo->id }})" class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                                        Editar
                                    </button>
                                    <button type="button" wire:click="alternarEstado({{ $tipo->id }})" class="text-xs font-semibold {{ $tipo->activo ? 'text-amber-700 hover:text-amber-900' : 'text-emerald-700 hover:text-emerald-900' }}">
                                        {{ $tipo->activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                                    No se encontraron tipos de servicio con los criterios seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif ($tab === 'operativos')
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Nombre del operativo</th>
                            <th class="px-6 py-3">Departamento</th>
                            <th class="px-6 py-3">Usuario vinculado</th>
                            <th class="px-6 py-3">Asignaciones</th>
                            <th class="px-6 py-3">Estado</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($registros as $operativo)
                            <tr wire:key="operativo-{{ $operativo->id }}" class="hover:bg-slate-50/70">
                                <td class="px-6 py-3.5 font-medium text-slate-800">{{ $operativo->nombre }}</td>
                                <td class="px-6 py-3.5 text-slate-600">
                                    {{ $operativo->departamento?->siglas }} — {{ $operativo->departamento?->nombre }}
                                </td>
                                <td class="px-6 py-3.5 text-slate-600">
                                    @if ($operativo->usuario)
                                        <span class="font-medium text-slate-700">{{ $operativo->usuario->name }}</span>
                                        <span class="block text-xs text-slate-400">{{ $operativo->usuario->username ?? $operativo->usuario->email }}</span>
                                    @else
                                        <span class="text-slate-400">Sin cuenta vinculada</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3.5 text-slate-600">{{ $operativo->asignaciones_count }}</td>
                                <td class="px-6 py-3.5">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $operativo->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $operativo->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5 text-right whitespace-nowrap space-x-2">
                                    <button type="button" wire:click="abrirEditar({{ $operativo->id }})" class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                                        Editar
                                    </button>
                                    <button type="button" wire:click="alternarEstado({{ $operativo->id }})" class="text-xs font-semibold {{ $operativo->activo ? 'text-amber-700 hover:text-amber-900' : 'text-emerald-700 hover:text-emerald-900' }}">
                                        {{ $operativo->activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                    No se encontraron operativos con los criterios seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        @if ($registros->hasPages())
            <div class="px-6 py-4 border-t border-slate-100">
                {{ $registros->links() }}
            </div>
        @endif
    </div>

    {{-- Modal de creación / edición --}}
    <x-dialog-modal wire:model.live="modalAbierto">
        <x-slot name="title">
            {{ $registroEditandoId !== null ? 'Editar registro' : 'Nuevo registro' }}
            —
            @switch($tab)
                @case('areas') Área solicitante @break
                @case('departamentos') Departamento @break
                @case('tipos_servicio') Tipo de servicio @break
                @case('operativos') Personal operativo @break
            @endswitch
        </x-slot>

        <x-slot name="content">
            <form wire:submit="guardar" id="form-catalogo" class="space-y-4">
                @if ($tab === 'areas')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-label for="area_clave" value="Clave *" />
                            <x-input id="area_clave" type="text" class="mt-1 block w-full uppercase" wire:model="form.clave" maxlength="50" />
                            <x-input-error for="form.clave" class="mt-1" />
                        </div>
                        <div>
                            <x-label for="area_tipo" value="Tipo de área *" />
                            <x-input id="area_tipo" type="text" list="tipos-area-sugeridos" class="mt-1 block w-full" wire:model="form.tipo_area" maxlength="100" />
                            <datalist id="tipos-area-sugeridos">
                                <option value="Dirección" />
                                <option value="Subdirección" />
                                <option value="Coordinación" />
                                <option value="Departamento" />
                                <option value="Unidad" />
                            </datalist>
                            <x-input-error for="form.tipo_area" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-label for="area_nombre" value="Nombre del área *" />
                        <x-input id="area_nombre" type="text" class="mt-1 block w-full" wire:model="form.nombre" maxlength="255" />
                        <x-input-error for="form.nombre" class="mt-1" />
                    </div>

                    <div>
                        <x-label for="area_titular" value="Titular" />
                        <x-input id="area_titular" type="text" class="mt-1 block w-full" wire:model="form.titular" maxlength="255" />
                        <x-input-error for="form.titular" class="mt-1" />
                    </div>
                @elseif ($tab === 'departamentos')
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <x-label for="depto_siglas" value="Siglas *" />
                            <x-input id="depto_siglas"
                                     type="text"
                                     class="mt-1 block w-full uppercase"
                                     wire:model="form.siglas"
                                     maxlength="12"
                                     :disabled="$departamentoProtegido" />
                            <x-input-error for="form.siglas" class="mt-1" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-label for="depto_nombre" value="Nombre *" />
                            <x-input id="depto_nombre" type="text" class="mt-1 block w-full" wire:model="form.nombre" maxlength="255" />
                            <x-input-error for="form.nombre" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-label for="depto_titular" value="Titular responsable" />
                        <x-input id="depto_titular" type="text" class="mt-1 block w-full" wire:model="form.titular_responsable" maxlength="255" />
                        <x-input-error for="form.titular_responsable" class="mt-1" />
                    </div>

                    <div>
                        <x-label for="depto_ubicacion" value="Ubicación de oficina" />
                        <x-input id="depto_ubicacion" type="text" class="mt-1 block w-full" wire:model="form.ubicacion_oficina" maxlength="255" />
                        <x-input-error for="form.ubicacion_oficina" class="mt-1" />
                    </div>
                @elseif ($tab === 'tipos_servicio')
                    <div>
                        <x-label for="tipo_descripcion" value="Descripción *" />
                        <x-input id="tipo_descripcion" type="text" class="mt-1 block w-full" wire:model="form.descripcion" maxlength="255" />
                        <x-input-error for="form.descripcion" class="mt-1" />
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <x-checkbox wire:model="form.requiere_cantidad" />
                        <span>Requiere capturar cantidad solicitada en la partida</span>
                    </label>
                @elseif ($tab === 'operativos')
                    <div>
                        <x-label for="op_nombre" value="Nombre completo *" />
                        <x-input id="op_nombre" type="text" class="mt-1 block w-full" wire:model="form.nombre" maxlength="255" />
                        <x-input-error for="form.nombre" class="mt-1" />
                    </div>

                    <div>
                        <x-label for="op_depto" value="Departamento *" />
                        <select id="op_depto" wire:model.live="form.departamento_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="">Selecciona un departamento...</option>
                            @foreach ($departamentos as $depto)
                                <option value="{{ $depto->id }}">{{ $depto->siglas }} — {{ $depto->nombre }}{{ $depto->activo ? '' : ' (Inactivo)' }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="form.departamento_id" class="mt-1" />
                    </div>

                    <div>
                        <x-label for="op_usuario" value="Cuenta de usuario vinculada (opcional)" />
                        <select id="op_usuario" wire:model="form.user_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="">Sin cuenta vinculada</option>
                            @foreach ($usuariosDisponibles as $usuario)
                                <option value="{{ $usuario->id }}">
                                    {{ $usuario->name }} ({{ $usuario->username ?? $usuario->email }}){{ $usuario->departamento ? ' — '.$usuario->departamento->siglas : '' }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error for="form.user_id" class="mt-1" />
                    </div>
                @endif

                <div class="pt-2 border-t border-slate-100">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <x-checkbox wire:model="form.activo" :disabled="$departamentoProtegido" />
                        <span>Registro activo</span>
                    </label>
                    <x-input-error for="form.activo" class="mt-1" />
                </div>
            </form>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="cerrarModal" wire:loading.attr="disabled">
                Cancelar
            </x-secondary-button>

            <x-button class="ms-3" type="submit" form="form-catalogo" wire:loading.attr="disabled">
                Guardar
            </x-button>
        </x-slot>
    </x-dialog-modal>
        </div>
    </div>
</div>
