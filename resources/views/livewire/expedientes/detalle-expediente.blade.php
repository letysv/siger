<div>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Detalle del expediente') }} #{{ $expediente->id }}
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Información del oficio, partidas registradas y documentación privada.
                </p>
            </div>

            <a href="{{ route('expedientes.index') }}" class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                ← Volver a expedientes
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    @if (session('expediente_status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('expediente_status') }}
        </div>
    @endif

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

    @if ($partidasDevueltasCount > 0)
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 font-medium">
            Advertencia: este expediente tiene {{ $partidasDevueltasCount }} {{ $partidasDevueltasCount === 1 ? 'partida' : 'partidas' }} en estado Devuelto al área pendientes de aclaración.
        </div>
    @endif

    {{-- Encabezado del expediente --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-lg font-extrabold text-siger-900">Expediente #{{ $expediente->id }}</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-siger-100 text-siger-800">
                        {{ $expediente->estado?->nombre }}
                    </span>
                    @if ($expediente->es_via_rapida)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                            Vía rápida
                        </span>
                    @endif
                </div>
                <h3 class="mt-2 text-base font-semibold text-slate-800">{{ $expediente->asunto }}</h3>
            </div>

            <div class="text-xs text-slate-500 sm:text-right space-y-1">
                <div>Ejercicio: <span class="font-semibold text-slate-700">{{ $expediente->ejercicio }}</span></div>
                <div>Recepción: <span class="font-semibold text-slate-700">{{ $expediente->fecha_recepcion?->format('d/m/Y H:i') }}</span></div>
                <div>Capturó: <span class="font-semibold text-slate-700">{{ $expediente->usuario?->name }}</span></div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Número de oficio</div>
                <div class="mt-1 font-mono text-slate-800">{{ $expediente->numero_oficio ?: 'Sin número' }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Fecha de oficio</div>
                <div class="mt-1 text-slate-800">{{ $expediente->fecha_oficio?->format('d/m/Y') ?: '—' }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Área solicitante</div>
                <div class="mt-1 text-slate-800">{{ $expediente->areaSolicitante?->clave }} — {{ $expediente->areaSolicitante?->nombre }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Solicitante / Dirigido a</div>
                <div class="mt-1 text-slate-800">{{ $expediente->nombre_solicitante }}</div>
                <div class="text-xs text-slate-500">Para: {{ $expediente->dirigido_a }}</div>
            </div>
        </div>

        @if ($expediente->descripcion)
            <div class="pt-3 border-t border-slate-100 text-sm text-slate-700">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Descripción</div>
                <p class="whitespace-pre-line">{{ $expediente->descripcion }}</p>
            </div>
        @endif

        @if ($expediente->folios->isNotEmpty())
            <div class="pt-3 border-t border-slate-100">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Folios departamentales generados</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($expediente->folios as $folio)
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-lg bg-cyan-50 border border-cyan-200 text-xs font-mono font-semibold text-cyan-900">
                            <span>{{ $folio->departamento?->siglas }}:</span>
                            <span>{{ $folio->folio }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Partidas del expediente --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
            <div>
                <h4 class="font-bold text-slate-800">Partidas del expediente</h4>
                <p class="text-xs text-slate-500">Cada partida conserva su número único e historial independiente.</p>
            </div>

            @if ($puedeAgregarPartida)
                <button type="button"
                        wire:click="abrirModalPartida"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-siger-700 hover:bg-siger-800 text-white text-xs font-semibold uppercase tracking-wider rounded-lg">
                    + Agregar partida
                </button>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-3">#</th>
                        <th class="px-6 py-3">Concepto / Tipo de servicio</th>
                        <th class="px-6 py-3">Departamento</th>
                        <th class="px-6 py-3">Folio</th>
                        <th class="px-6 py-3">Cantidad</th>
                        <th class="px-6 py-3">Estado</th>
                        <th class="px-6 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($partidas as $partida)
                        <tr wire:key="partida-{{ $partida->id }}">
                            <td class="px-6 py-3.5 font-mono font-bold text-siger-800">#{{ $partida->numero_partida }}</td>
                            <td class="px-6 py-3.5">
                                <div class="font-medium text-slate-800">{{ $partida->concepto }}</div>
                                @if ($partida->tipoServicio)
                                    <div class="text-xs text-slate-500">{{ $partida->tipoServicio->descripcion }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-3.5 text-slate-600">
                                @if ($partida->departamento)
                                    <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">{{ $partida->departamento->siglas }}</span>
                                    <span class="ms-1 text-xs">{{ $partida->departamento->nombre }}</span>
                                @else
                                    <span class="text-xs text-slate-400">Sin asignar</span>
                                @endif
                            </td>
                            <td class="px-6 py-3.5 font-mono text-xs text-slate-600">
                                {{ $partida->folio?->folio ?? 'Sin turnar' }}
                            </td>
                            <td class="px-6 py-3.5 text-slate-600 text-xs">
                                @if ($partida->cantidad_solicitada !== null)
                                    {{ number_format((float) $partida->cantidad_solicitada, 2) }} {{ $partida->unidad_medida }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                    {{ $partida->estado?->nombre }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-right whitespace-nowrap">
                                @can('update', $partida)
                                    <button type="button" wire:click="abrirEditarPartida({{ $partida->id }})" class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                                        Editar
                                    </button>
                                @else
                                    <span class="text-xs text-slate-300">—</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-6 text-center text-slate-500">
                                No hay partidas visibles para tu alcance actual.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Documentos adjuntos y acuses --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Archivos del expediente --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            <h4 class="font-bold text-slate-800">Archivos digitales del oficio</h4>

            @if ($puedeAdjuntar)
                <form wire:submit="subirArchivo" class="flex flex-col sm:flex-row gap-2 items-start sm:items-center border-b border-slate-100 pb-4">
                    <input type="file" accept="application/pdf,.pdf" wire:model="nuevoArchivo"
                           class="block w-full text-xs text-slate-600 file:me-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-siger-50 file:text-siger-700" />
                    <x-button type="submit" wire:loading.attr="disabled" class="shrink-0">
                        Adjuntar PDF
                    </x-button>
                </form>
                <x-input-error for="nuevoArchivo" class="mt-1" />
            @endif

            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($expediente->archivos as $archivo)
                    <li class="py-2.5 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-medium text-slate-800 truncate">{{ $archivo->nombre }}</div>
                            <div class="text-[11px] text-slate-400">{{ $archivo->created_at?->format('d/m/Y H:i') }}</div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            @can('view', $archivo)
                                <a href="{{ route('expedientes.archivos.ver', $archivo) }}" target="_blank" class="text-xs font-semibold text-siger-700 hover:text-siger-900">Ver</a>
                            @endcan
                            @can('download', $archivo)
                                <a href="{{ route('expedientes.archivos.descargar', $archivo) }}" class="text-xs font-semibold text-emerald-700 hover:text-emerald-900">Descargar</a>
                            @endcan
                        </div>
                    </li>
                @empty
                    <li class="py-4 text-xs text-slate-400">No hay archivos digitales adjuntos.</li>
                @endforelse
            </ul>
        </div>

        {{-- Acuses --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h4 class="font-bold text-slate-800">Acuses de recibo</h4>
                @if ($puedeAdjuntar)
                    <button type="button" wire:click="abrirModalAcuse()" class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                        + Registrar acuse
                    </button>
                @endif
            </div>

            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($acusesVigentes as $acuse)
                    <li class="py-3 space-y-1">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-medium text-slate-800 truncate">
                                    {{ $acuse->nombre }}
                                    <span class="ms-1 text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700">Vigente</span>
                                </div>
                                <div class="text-[11px] text-slate-400">{{ $acuse->created_at?->format('d/m/Y H:i') }}</div>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                @can('view', $acuse)
                                    <a href="{{ route('expedientes.acuses.ver', $acuse) }}" target="_blank" class="text-xs font-semibold text-siger-700 hover:text-siger-900">Ver</a>
                                @endcan
                                @can('download', $acuse)
                                    <a href="{{ route('expedientes.acuses.descargar', $acuse) }}" class="text-xs font-semibold text-emerald-700 hover:text-emerald-900">Descargar</a>
                                @endcan
                                @if ($puedeAdjuntar)
                                    <button type="button" wire:click="abrirModalAcuse({{ $acuse->id }})" class="text-xs font-semibold text-amber-700 hover:text-amber-900">
                                        Sustituir
                                    </button>
                                @endif
                            </div>
                        </div>

                        @if ($acuse->motivo_sustitucion)
                            <div class="text-xs text-slate-500">
                                Sustituye al acuse #{{ $acuse->acuse_original_id }} ({{ $acuse->usuarioSustitucion?->name }}): {{ $acuse->motivo_sustitucion }}
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="py-4 text-xs text-slate-400">No hay acuses vigentes registrados.</li>
                @endforelse
            </ul>

            @if ($acusesHistoricos->isNotEmpty())
                <details class="pt-3 border-t border-slate-100 text-xs">
                    <summary class="cursor-pointer font-semibold text-slate-500 hover:text-slate-700">
                        Historial de acuses sustituidos ({{ $acusesHistoricos->count() }})
                    </summary>
                    <ul class="mt-2 divide-y divide-slate-100">
                        @foreach ($acusesHistoricos as $acuseHist)
                            <li class="py-2 flex items-center justify-between gap-2 text-slate-500">
                                <span class="truncate">{{ $acuseHist->nombre }} · {{ $acuseHist->created_at?->format('d/m/Y H:i') }}</span>
                                <span class="flex items-center gap-2 shrink-0">
                                    @can('view', $acuseHist)
                                        <a href="{{ route('expedientes.acuses.ver', $acuseHist) }}" target="_blank" class="font-semibold text-siger-700 hover:text-siger-900">Ver</a>
                                    @endcan
                                    @can('download', $acuseHist)
                                        <a href="{{ route('expedientes.acuses.descargar', $acuseHist) }}" class="font-semibold text-emerald-700 hover:text-emerald-900">Descargar</a>
                                    @endcan
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    </div>

    {{-- Modal agregar / editar partida --}}
    <x-dialog-modal wire:model.live="modalPartidaAbierto">
        <x-slot name="title">
            {{ $partidaEditandoId !== null ? 'Editar partida en preparación' : 'Agregar partida al expediente #'.$expediente->id }}
        </x-slot>

        <x-slot name="content">
            <form wire:submit="guardarPartida" id="form-nueva-partida" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-label for="p_tipo" value="Tipo de servicio" />
                        <select id="p_tipo" wire:model.live="partidaForm.tipo_servicio_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="">Por definir</option>
                            @foreach ($tiposServicio as $tipo)
                                <option value="{{ $tipo->id }}">{{ $tipo->descripcion }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="partidaForm.tipo_servicio_id" class="mt-1" />
                    </div>

                    <div>
                        <x-label for="p_depto" value="Departamento propuesto" />
                        <select id="p_depto" wire:model="partidaForm.departamento_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="">Sin asignar</option>
                            @foreach ($departamentos as $depto)
                                <option value="{{ $depto->id }}">{{ $depto->siglas }} — {{ $depto->nombre }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="partidaForm.departamento_id" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-label for="p_concepto" value="Concepto *" />
                    <textarea id="p_concepto" rows="2" wire:model="partidaForm.concepto" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                    <x-input-error for="partidaForm.concepto" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-label for="p_cantidad" :value="$requiereCantidad ? 'Cantidad solicitada *' : 'Cantidad solicitada'" />
                        <x-input id="p_cantidad" type="number" step="0.01" min="0.01" class="mt-1 block w-full" wire:model="partidaForm.cantidad_solicitada" />
                        <x-input-error for="partidaForm.cantidad_solicitada" class="mt-1" />
                    </div>

                    <div>
                        <x-label for="p_unidad" :value="$requiereCantidad ? 'Unidad de medida *' : 'Unidad de medida'" />
                        <x-input id="p_unidad" type="text" class="mt-1 block w-full" wire:model="partidaForm.unidad_medida" maxlength="100" />
                        <x-input-error for="partidaForm.unidad_medida" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-label for="p_obs" value="Observaciones" />
                    <textarea id="p_obs" rows="2" wire:model="partidaForm.observaciones" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                    <x-input-error for="partidaForm.observaciones" class="mt-1" />
                </div>
            </form>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="cerrarModalPartida">Cancelar</x-secondary-button>
            <x-button class="ms-3" type="submit" form="form-nueva-partida">Guardar partida</x-button>
        </x-slot>
    </x-dialog-modal>

    {{-- Modal acuse / sustitución --}}
    <x-dialog-modal wire:model.live="modalAcuseAbierto" maxWidth="md">
        <x-slot name="title">
            {{ $acuseOriginalId !== null ? 'Sustituir acuse #' . $acuseOriginalId : 'Registrar acuse de recibo' }}
        </x-slot>

        <x-slot name="content">
            <form wire:submit="guardarAcuse" id="form-acuse-expediente" class="space-y-4">
                <div>
                    <x-label for="nuevo_acuse_file" value="Archivo del acuse (solo PDF, máx. 10 MB) *" />
                    <input id="nuevo_acuse_file" type="file" accept="application/pdf,.pdf" wire:model="nuevoAcuse"
                           class="mt-1 block w-full text-sm text-slate-600 file:me-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-siger-50 file:text-siger-700" />
                    <x-input-error for="nuevoAcuse" class="mt-1" />
                </div>

                @if ($acuseOriginalId !== null)
                    <div>
                        <x-label for="motivo_sust" value="Motivo de sustitución *" />
                        <textarea id="motivo_sust" rows="2" wire:model="motivoSustitucion"
                                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                        <x-input-error for="motivoSustitucion" class="mt-1" />
                    </div>
                @endif
            </form>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="cerrarModalAcuse">Cancelar</x-secondary-button>
            <x-button class="ms-3" type="submit" form="form-acuse-expediente">Guardar acuse</x-button>
        </x-slot>
    </x-dialog-modal>
        </div>
    </div>
</div>
