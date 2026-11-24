<div>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Partidas de servicio') }}
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Control de turnado formal, generación de folios departamentales, re-turnado y cancelación.
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Indicadores rápidos --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Partidas visibles</p>
                    <p class="mt-1 text-2xl font-extrabold text-siger-900">{{ number_format($resumen['total']) }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Por turnar (sin folio)</p>
                    <p class="mt-1 text-2xl font-extrabold text-amber-600">{{ number_format($resumen['pendientes_turnar']) }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Turnadas / En atención</p>
                    <p class="mt-1 text-2xl font-extrabold text-cyan-700">{{ number_format($resumen['en_proceso']) }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Canceladas</p>
                    <p class="mt-1 text-2xl font-extrabold text-slate-600">{{ number_format($resumen['canceladas']) }}</p>
                </div>
            </div>

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

            {{-- Filtros y tabla --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200">
                <div class="p-4 sm:p-6 border-b border-slate-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <x-input type="search"
                             wire:model.live.debounce.300ms="buscar"
                             placeholder="Folio, oficio, asunto o concepto..."
                             class="w-full text-sm lg:col-span-2" />

                    <select wire:model.live="departamentoFiltro"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">Todos los departamentos</option>
                        <option value="sin_departamento">Sin departamento</option>
                        @foreach ($departamentos as $depto)
                            <option value="{{ $depto->id }}">{{ $depto->siglas }} — {{ $depto->nombre }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="estadoFiltro"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">Todos los estados</option>
                        @foreach ($estados as $estado)
                            <option value="{{ $estado->id }}">{{ $estado->nombre }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="ejercicioFiltro"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">Todos los años</option>
                        @foreach ($ejercicios as $anio)
                            <option value="{{ $anio }}">{{ $anio }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Expediente / Partida</th>
                                <th class="px-6 py-3">Concepto</th>
                                <th class="px-6 py-3">Departamento / Folio</th>
                                <th class="px-6 py-3">Estado</th>
                                <th class="px-6 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($partidas as $partida)
                                @php
                                    $puedeTurnar = $departamentosActivos->contains(fn ($d) => \Illuminate\Support\Facades\Gate::allows('turnar', [$partida, $d]));
                                    $puedeReturnar = $departamentosActivos->contains(fn ($d) => \Illuminate\Support\Facades\Gate::allows('returnar', [$partida, $d]));
                                    $puedeRegresarDireccion = \Illuminate\Support\Facades\Gate::allows('regresarDireccion', $partida);
                                    $puedeCancelar = \Illuminate\Support\Facades\Gate::allows('cancelar', $partida);
                                @endphp
                                <tr wire:key="partida-row-{{ $partida->id }}" class="hover:bg-slate-50/70">
                                    <td class="px-6 py-3.5 whitespace-nowrap">
                                        <div class="font-semibold text-siger-800">
                                            Exp. #{{ $partida->servicio_id }} · Partida #{{ $partida->numero_partida }}
                                        </div>
                                        <div class="text-xs text-slate-500 font-mono">
                                            {{ $partida->servicio?->numero_oficio ?: 'Sin número de oficio' }}
                                        </div>
                                        <div class="text-[11px] text-slate-400">
                                            {{ $partida->servicio?->areaSolicitante?->clave }}
                                            @if ($partida->servicio?->es_via_rapida)
                                                · <span class="text-amber-700 font-semibold">Vía rápida</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-6 py-3.5 max-w-md">
                                        <div class="font-medium text-slate-800 line-clamp-2">{{ $partida->concepto }}</div>
                                        @if ($partida->tipoServicio)
                                            <div class="text-xs text-slate-500">{{ $partida->tipoServicio->descripcion }}</div>
                                        @endif
                                    </td>

                                    <td class="px-6 py-3.5">
                                        @if ($partida->departamento)
                                            <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">
                                                {{ $partida->departamento->siglas }}
                                            </span>
                                            <span class="ms-1 text-xs text-slate-600">{{ $partida->departamento->nombre }}</span>
                                        @else
                                            <span class="text-xs text-slate-400">Sin departamento</span>
                                        @endif

                                        <div class="mt-1">
                                            @if ($partida->folio)
                                                <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-cyan-50 text-cyan-900 border border-cyan-200">
                                                    {{ $partida->folio->folio }}
                                                </span>
                                            @else
                                                <span class="text-[11px] text-slate-400">Sin folio asignado</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-6 py-3.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $partida->estado?->clave === 'CANCELADO' ? 'bg-red-100 text-red-800' : ($partida->estado?->clave === 'TURNADO' ? 'bg-cyan-100 text-cyan-800' : 'bg-siger-100 text-siger-800') }}">
                                            {{ $partida->estado?->nombre }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-3.5 text-right whitespace-nowrap space-x-2">
                                        <button type="button" wire:click="abrirDetalle({{ $partida->id }})" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
                                            Historial
                                        </button>

                                        @if ($puedeTurnar)
                                            <button type="button" wire:click="abrirTurnar({{ $partida->id }})" class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                                                Turnar
                                            </button>
                                        @endif

                                        @if ($puedeReturnar)
                                            <button type="button" wire:click="abrirReturnar({{ $partida->id }})" class="text-xs font-semibold text-indigo-700 hover:text-indigo-900">
                                                Re-turnar
                                            </button>
                                        @endif

                                        @if ($puedeRegresarDireccion)
                                            <button type="button" wire:click="abrirRegresarDireccion({{ $partida->id }})" class="text-xs font-semibold text-amber-700 hover:text-amber-900">
                                                Regresar a Dirección
                                            </button>
                                        @endif

                                        @if ($puedeCancelar)
                                            <button type="button" wire:click="abrirCancelar({{ $partida->id }})" class="text-xs font-semibold text-red-700 hover:text-red-900">
                                                Cancelar
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                                        No se encontraron partidas con los criterios seleccionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($partidas->hasPages())
                    <div class="px-6 py-4 border-t border-slate-100">
                        {{ $partidas->links() }}
                    </div>
                @endif
            </div>

            {{-- Modal Turnar / Re-turnar --}}
            <x-dialog-modal wire:model.live="modalTurnadoAbierto" maxWidth="lg">
                <x-slot name="title">
                    {{ $modoTurnado === 'returnar' ? 'Re-turnar partida' : 'Turnar partida' }}
                    @if ($partidaModal)
                        #{{ $partidaModal->numero_partida }} (Exp. #{{ $partidaModal->servicio_id }})
                    @endif
                </x-slot>

                <x-slot name="content">
                    <form wire:submit="ejecutarTurnado" id="form-turnado-partida" class="space-y-4">
                        @if ($partidaModal)
                            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-xs text-slate-600 space-y-1">
                                <div class="font-semibold text-slate-800">{{ $partidaModal->concepto }}</div>
                                <div>
                                    @if ($modoTurnado === 'returnar')
                                        Departamento actual: <span class="font-semibold">{{ $partidaModal->departamento?->siglas }}</span>
                                        · Folio actual: <span class="font-mono font-semibold">{{ $partidaModal->folio?->folio }}</span>
                                    @else
                                        Si el expediente ya tiene folio en el departamento destino, se reutilizará; de lo contrario se generará uno nuevo (<span class="font-mono">SIGLA/MES/CONSECUTIVO/AÑO</span>).
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div>
                            <x-label for="depto_destino" value="Departamento destino *" />
                            <select id="depto_destino" wire:model="departamentoDestinoId"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                <option value="">Selecciona un departamento...</option>
                                @foreach ($destinosTurnado as $depto)
                                    <option value="{{ $depto->id }}">{{ $depto->siglas }} — {{ $depto->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error for="departamentoDestinoId" class="mt-1" />
                        </div>

                        <div>
                            <x-label for="motivo_turnado" :value="$modoTurnado === 'returnar' ? 'Motivo del re-turnado *' : 'Observaciones / motivo (opcional)'" />
                            <textarea id="motivo_turnado" rows="3" wire:model="motivoTurnado"
                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                            <x-input-error for="motivoTurnado" class="mt-1" />
                        </div>
                    </form>
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="cerrarModalTurnado">Cancelar</x-secondary-button>
                    <x-button class="ms-3" type="submit" form="form-turnado-partida" wire:loading.attr="disabled">
                        {{ $modoTurnado === 'returnar' ? 'Confirmar re-turnado' : 'Confirmar turnado' }}
                    </x-button>
                </x-slot>
            </x-dialog-modal>

            {{-- Modal Regresar a Dirección (Jefe) --}}
            <x-dialog-modal wire:model.live="modalRegresarDireccionAbierto" maxWidth="md">
                <x-slot name="title">
                    Regresar partida a Dirección
                    @if ($partidaModal)
                        #{{ $partidaModal->numero_partida }} (Exp. #{{ $partidaModal->servicio_id }})
                    @endif
                </x-slot>

                <x-slot name="content">
                    <form wire:submit="ejecutarRegresoDireccion" id="form-regresar-direccion" class="space-y-4">
                        <p class="text-xs text-slate-600">
                            La partida pasará a estado <strong>En revisión</strong> conservando su folio histórico y cerrando asignaciones operativas activas, para que Dirección la re-turne al departamento correspondiente.
                        </p>

                        <div>
                            <x-label for="motivo_regreso_direccion" value="Motivo para regresar a Dirección *" />
                            <textarea id="motivo_regreso_direccion" rows="3" wire:model="motivoRegresoDireccion"
                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                            <x-input-error for="motivoRegresoDireccion" class="mt-1" />
                        </div>
                    </form>
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="cerrarModalRegresarDireccion">Cancelar</x-secondary-button>
                    <x-button class="ms-3" type="submit" form="form-regresar-direccion" wire:loading.attr="disabled">
                        Regresar a Dirección
                    </x-button>
                </x-slot>
            </x-dialog-modal>

            {{-- Modal Cancelar --}}
            <x-dialog-modal wire:model.live="modalCancelarAbierto" maxWidth="md">
                <x-slot name="title">
                    Cancelar partida
                    @if ($partidaModal)
                        #{{ $partidaModal->numero_partida }} (Exp. #{{ $partidaModal->servicio_id }})
                    @endif
                </x-slot>

                <x-slot name="content">
                    <form wire:submit="ejecutarCancelacion" id="form-cancelar-partida" class="space-y-4">
                        <p class="text-xs text-slate-600">
                            La cancelación es definitiva y conservará todo el historial registrado.
                        </p>

                        <div>
                            <x-label for="motivo_cancelacion" value="Motivo de cancelación *" />
                            <textarea id="motivo_cancelacion" rows="3" wire:model="motivoCancelacion"
                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                            <x-input-error for="motivoCancelacion" class="mt-1" />
                        </div>
                    </form>
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="cerrarModalCancelar">Cerrar</x-secondary-button>
                    <x-danger-button class="ms-3" type="submit" form="form-cancelar-partida" wire:loading.attr="disabled">
                        Cancelar partida
                    </x-danger-button>
                </x-slot>
            </x-dialog-modal>

            {{-- Modal Detalle e Historial de Turnados --}}
            <x-dialog-modal wire:model.live="modalDetalleAbierto" maxWidth="2xl">
                <x-slot name="title">
                    @if ($partidaModal)
                        Partida #{{ $partidaModal->numero_partida }} — Expediente #{{ $partidaModal->servicio_id }}
                    @else
                        Detalle de partida
                    @endif
                </x-slot>

                <x-slot name="content">
                    @if ($partidaModal)
                        <div class="space-y-4 text-sm">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-slate-50 border border-slate-200 rounded-lg p-4">
                                <div>
                                    <div class="text-xs text-slate-400 font-semibold uppercase">Folio asignado</div>
                                    <div class="font-mono font-bold text-cyan-900">{{ $partidaModal->folio?->folio ?? 'Sin turnar' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-slate-400 font-semibold uppercase">Estado</div>
                                    <div class="font-semibold text-slate-800">{{ $partidaModal->estado?->nombre }}</div>
                                </div>
                                <div class="sm:col-span-2">
                                    <div class="text-xs text-slate-400 font-semibold uppercase">Concepto</div>
                                    <div class="text-slate-800">{{ $partidaModal->concepto }}</div>
                                </div>
                            </div>

                            @if ($partidaModal->estado?->clave === 'CANCELADO')
                                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-900 space-y-1">
                                    <div class="font-semibold">
                                        Cancelada por {{ $partidaModal->usuarioCancelacion?->name }} el {{ $partidaModal->fecha_cancelacion?->format('d/m/Y H:i') }}
                                    </div>
                                    <div>Motivo: {{ $partidaModal->motivo_cancelacion }}</div>
                                </div>
                            @endif

                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Historial de turnados</h4>
                                    @can('view', $partidaModal->servicio)
                                        <a href="{{ route('expedientes.show', $partidaModal->servicio) }}" class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                                            Ir al expediente →
                                        </a>
                                    @endcan
                                </div>

                                @if ($partidaModal->turnados->isEmpty())
                                    <p class="text-xs text-slate-400">Esta partida aún no tiene movimientos de turnado.</p>
                                @else
                                    <div class="space-y-2">
                                        @foreach ($partidaModal->turnados->sortByDesc('id') as $mov)
                                            <div class="border border-slate-200 rounded-lg p-3 text-xs space-y-1">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-semibold text-slate-800">
                                                        {{ $mov->departamentoOrigen?->siglas ?? 'Recepción' }} → {{ $mov->departamentoDestino?->siglas }}
                                                    </span>
                                                    <span class="text-slate-400">{{ $mov->fecha?->format('d/m/Y H:i') }}</span>
                                                </div>
                                                <div class="font-mono text-cyan-800">
                                                    Folio: {{ $mov->folioOrigen?->folio ? $mov->folioOrigen->folio.' → ' : '' }}{{ $mov->folioDestino?->folio }}
                                                </div>
                                                <div class="text-slate-500">Realizó: {{ $mov->usuario?->name }}</div>
                                                @if ($mov->motivo)
                                                    <div class="text-slate-700">Motivo: {{ $mov->motivo }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="cerrarDetalle">Cerrar</x-secondary-button>
                </x-slot>
            </x-dialog-modal>
        </div>
    </div>
</div>
