<div>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Expedientes') }}
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Recepción y consulta de oficios, partidas iniciales y documentos adjuntos.
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    @if (session('expediente_status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('expediente_status') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-slate-200">
        {{-- Filtros y botón de registro --}}
        <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 flex-1">
                <x-input type="search"
                         wire:model.live.debounce.300ms="buscar"
                         placeholder="Oficio, asunto, solicitante o folio..."
                         class="w-full text-sm lg:col-span-2" />

                <select wire:model.live="areaFiltro"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                    <option value="">Todas las áreas</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->id }}">{{ $area->clave }} — {{ $area->nombre }}</option>
                    @endforeach
                </select>

                <select wire:model.live="estadoFiltro"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                    <option value="">Todos los estados</option>
                    @foreach ($estados as $estado)
                        <option value="{{ $estado->id }}">{{ $estado->nombre }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2">
                    <select wire:model.live="ejercicioFiltro"
                            class="w-1/2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">Año</option>
                        @foreach ($ejercicios as $anio)
                            <option value="{{ $anio }}">{{ $anio }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="viaRapidaFiltro"
                            class="w-1/2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">Vía</option>
                        <option value="1">Vía rápida</option>
                        <option value="0">Ordinaria</option>
                    </select>
                </div>
            </div>

            @if ($puedeCrear)
                <div class="flex items-center justify-end">
                    <a href="{{ route('expedientes.create') }}"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-siger-700 hover:bg-siger-800 text-white text-xs font-semibold uppercase tracking-wider rounded-lg shadow-sm transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Registrar expediente</span>
                    </a>
                </div>
            @endif
        </div>

        {{-- Tabla de expedientes --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Expediente / Oficio</th>
                        <th class="px-6 py-3">Área y solicitante</th>
                        <th class="px-6 py-3">Asunto</th>
                        <th class="px-6 py-3">Folios / Partidas</th>
                        <th class="px-6 py-3">Estado</th>
                        <th class="px-6 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($expedientes as $expediente)
                        <tr wire:key="exp-{{ $expediente->id }}" class="hover:bg-slate-50/70">
                            <td class="px-6 py-3.5 whitespace-nowrap">
                                <div class="font-semibold text-siger-800">
                                    #{{ $expediente->id }}
                                    @if ($expediente->es_via_rapida)
                                        <span class="ms-1 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800">
                                            Vía rápida
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-600 font-mono">
                                    {{ $expediente->numero_oficio ?: 'Sin número de oficio' }}
                                </div>
                                <div class="text-[11px] text-slate-400">
                                    Recibido: {{ $expediente->fecha_recepcion?->format('d/m/Y H:i') }}
                                </div>
                            </td>
                            <td class="px-6 py-3.5">
                                <div class="font-medium text-slate-800">
                                    {{ $expediente->areaSolicitante?->clave }} — {{ $expediente->areaSolicitante?->nombre }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ $expediente->nombre_solicitante }}
                                </div>
                            </td>
                            <td class="px-6 py-3.5 max-w-md">
                                <div class="text-slate-700 line-clamp-2">{{ $expediente->asunto }}</div>
                            </td>
                            <td class="px-6 py-3.5">
                                <div class="text-xs text-slate-700 font-medium">
                                    {{ $expediente->partidas_count }} {{ $expediente->partidas_count === 1 ? 'partida' : 'partidas' }}
                                    · {{ $expediente->archivos_count }} arch.
                                    · {{ $expediente->acuses_count }} acuse(s)
                                </div>
                                @if ($expediente->folios->isNotEmpty())
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach ($expediente->folios as $folio)
                                            <span class="font-mono text-[11px] px-1.5 py-0.5 rounded bg-cyan-50 text-cyan-800 border border-cyan-200">
                                                {{ $folio->folio }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-[11px] text-slate-400 mt-0.5">Sin folios turnados</div>
                                @endif
                            </td>
                            <td class="px-6 py-3.5 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-siger-100 text-siger-800">
                                    {{ $expediente->estado?->nombre }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-right whitespace-nowrap">
                                <a href="{{ route('expedientes.show', $expediente) }}"
                                   class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                                    Ver detalle
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                No hay expedientes visibles con los criterios seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($expedientes->hasPages())
            <div class="px-6 py-4 border-t border-slate-100">
                {{ $expedientes->links() }}
            </div>
        @endif
    </div>
        </div>
    </div>
</div>
