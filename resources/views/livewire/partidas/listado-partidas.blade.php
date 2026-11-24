<div>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Partidas de servicio') }}
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Turnado, folios departamentales, asignaciones operativas, devoluciones, conclusión y entregas.
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
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">En proceso</p>
                    <p class="mt-1 text-2xl font-extrabold text-cyan-700">{{ number_format($resumen['en_proceso']) }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Concluidas</p>
                    <p class="mt-1 text-2xl font-extrabold text-emerald-700">{{ number_format($resumen['concluidas']) }}</p>
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
                                <th class="px-6 py-3">Concepto / Operativos</th>
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
                                    $puedeOperativos = $this->puedeGestionarOperativos($partida);
                                    $puedeIniciar = \Illuminate\Support\Facades\Gate::allows('iniciarAtencion', $partida);
                                    $puedeDevolver = \Illuminate\Support\Facades\Gate::allows('devolver', $partida);
                                    $puedeReanudar = \Illuminate\Support\Facades\Gate::allows('reanudar', $partida);
                                    $puedeConcluir = \Illuminate\Support\Facades\Gate::allows('concluir', $partida);
                                    $puedeCancelar = \Illuminate\Support\Facades\Gate::allows('cancelar', $partida);
                                    $estadoClave = $partida->estado?->clave;
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
                                        @if ($partida->asignaciones->isNotEmpty())
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                @foreach ($partida->asignaciones as $asig)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-700">
                                                        {{ $asig->operativo?->nombre }}
                                                    </span>
                                                @endforeach
                                            </div>
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
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ match($estadoClave) {
                                            'CONCLUIDO' => 'bg-emerald-100 text-emerald-800',
                                            'CANCELADO' => 'bg-red-100 text-red-800',
                                            'DEVUELTO_AREA' => 'bg-amber-100 text-amber-800',
                                            'EN_ATENCION' => 'bg-indigo-100 text-indigo-800',
                                            'TURNADO' => 'bg-cyan-100 text-cyan-800',
                                            default => 'bg-siger-100 text-siger-800',
                                        } }}">
                                            {{ $partida->estado?->nombre }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-3.5 text-right">
                                        <div class="flex flex-wrap justify-end gap-x-2.5 gap-y-1">
                                            <button type="button" wire:click="abrirDetalle({{ $partida->id }})" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
                                                Detalle
                                            </button>

                                            @if ($puedeTurnar)
                                                <button type="button" wire:click="abrirTurnar({{ $partida->id }})" class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                                                    Turnar
                                                </button>
                                            @endif

                                            @if ($puedeOperativos)
                                                <button type="button" wire:click="abrirOperativos({{ $partida->id }})" class="text-xs font-semibold text-violet-700 hover:text-violet-900">
                                                    Operativos
                                                </button>
                                            @endif

                                            @if ($puedeIniciar)
                                                <button type="button" wire:click="iniciarAtencion({{ $partida->id }})" class="text-xs font-semibold text-cyan-700 hover:text-cyan-900">
                                                    Iniciar atención
                                                </button>
                                            @endif

                                            @if ($puedeDevolver)
                                                <button type="button" wire:click="abrirDevolver({{ $partida->id }})" class="text-xs font-semibold text-amber-700 hover:text-amber-900">
                                                    Devolver
                                                </button>
                                            @endif

                                            @if ($puedeReanudar)
                                                <button type="button" wire:click="abrirReanudar({{ $partida->id }})" class="text-xs font-semibold text-emerald-700 hover:text-emerald-900">
                                                    Subsanar
                                                </button>
                                            @endif

                                            @if ($puedeConcluir)
                                                <button type="button" wire:click="abrirConcluir({{ $partida->id }})" class="text-xs font-semibold text-emerald-700 hover:text-emerald-900">
                                                    Concluir
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
                                        </div>
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
                                        Si el expediente ya tiene folio en el departamento destino, se reutilizará; de lo contrario se generará uno nuevo (<span class="font-mono">SIGLA/MES/CONSECUTIVO/AA</span>).
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

            {{-- Modal Operativos asignados (RF-016) --}}
            <x-dialog-modal wire:model.live="modalOperativosAbierto" maxWidth="xl">
                <x-slot name="title">
                    Asignación de operativos
                    @if ($partidaModal)
                        — Partida #{{ $partidaModal->numero_partida }} ({{ $partidaModal->departamento?->siglas }})
                    @endif
                </x-slot>

                <x-slot name="content">
                    @if ($partidaModal)
                        <div class="space-y-4">
                            @if (Auth::user()?->checkPermissionTo('operativos_partida.asignar', 'web'))
                                <form wire:submit="asignarOperativo" class="flex flex-col sm:flex-row gap-2">
                                    <select wire:model="operativoAsignarId" class="flex-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                        <option value="">Selecciona un operativo activo de {{ $partidaModal->departamento?->siglas }}...</option>
                                        @foreach ($operativosDisponibles as $op)
                                            <option value="{{ $op->id }}">{{ $op->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <x-button type="submit" wire:loading.attr="disabled">Asignar</x-button>
                                </form>
                                <x-input-error for="operativoAsignarId" />
                            @endif

                            <div class="space-y-2">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Asignaciones registradas</h4>
                                @forelse ($partidaModal->asignaciones->sortByDesc('id') as $asig)
                                    <div class="border border-slate-200 rounded-lg p-3 text-xs space-y-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <span class="font-semibold text-slate-800">{{ $asig->operativo?->nombre }}</span>
                                                <span class="ms-2 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $asig->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' }}">
                                                    {{ $asig->activo ? 'Activa' : 'Finalizada' }}
                                                </span>
                                                <div class="text-slate-400 mt-0.5">
                                                    Inicio: {{ $asig->fecha_asignacion?->format('d/m/Y H:i') }}
                                                    @if ($asig->fecha_finalizacion)
                                                        · Fin: {{ $asig->fecha_finalizacion->format('d/m/Y H:i') }}
                                                    @endif
                                                </div>
                                            </div>

                                            @if ($asig->activo && \Illuminate\Support\Facades\Gate::allows('finalizar', $asig))
                                                <div class="flex items-center gap-2">
                                                    @if ($operativosDisponibles->isNotEmpty() && Auth::user()?->checkPermissionTo('operativos_partida.asignar', 'web'))
                                                        <button type="button" wire:click="iniciarSustitucion({{ $asig->id }})" class="font-semibold text-indigo-700 hover:text-indigo-900">
                                                            Sustituir
                                                        </button>
                                                    @endif
                                                    <button type="button" wire:click="finalizarAsignacion({{ $asig->id }})" class="font-semibold text-amber-700 hover:text-amber-900">
                                                        Finalizar
                                                    </button>
                                                </div>
                                            @endif
                                        </div>

                                        @if ($asignacionSustituirId === $asig->id)
                                            <form wire:submit="ejecutarSustitucion" class="pt-2 border-t border-slate-100 flex flex-col sm:flex-row gap-2">
                                                <select wire:model="operativoSustitutoId" class="flex-1 border-gray-300 rounded-md shadow-sm text-xs">
                                                    <option value="">Selecciona operativo sustituto...</option>
                                                    @foreach ($operativosDisponibles as $op)
                                                        <option value="{{ $op->id }}">{{ $op->nombre }}</option>
                                                    @endforeach
                                                </select>
                                                <x-button type="submit">Confirmar sustitución</x-button>
                                            </form>
                                            <x-input-error for="operativoSustitutoId" />
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-400">Aún no hay operativos asignados a esta partida.</p>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="cerrarModalOperativos">Cerrar</x-secondary-button>
                </x-slot>
            </x-dialog-modal>

            {{-- Modal Devolver al área (RF-018) --}}
            <x-dialog-modal wire:model.live="modalDevolverAbierto" maxWidth="md">
                <x-slot name="title">
                    Devolver partida al área solicitante
                    @if ($partidaModal) #{{ $partidaModal->numero_partida }} @endif
                </x-slot>

                <x-slot name="content">
                    <form wire:submit="ejecutarDevolucion" id="form-devolver-partida" class="space-y-4">
                        <p class="text-xs text-slate-600">
                            La partida cambiará a <strong>Devuelto al área</strong> conservando su folio y asignaciones activas en suspensión operativa.
                        </p>
                        <div>
                            <x-label for="obs_devolucion" value="Observaciones obligatorias *" />
                            <textarea id="obs_devolucion" rows="3" wire:model="observacionesDevolucion"
                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                            <x-input-error for="observacionesDevolucion" class="mt-1" />
                        </div>
                    </form>
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="cerrarModalDevolver">Cancelar</x-secondary-button>
                    <x-button class="ms-3" type="submit" form="form-devolver-partida" wire:loading.attr="disabled">
                        Confirmar devolución
                    </x-button>
                </x-slot>
            </x-dialog-modal>

            {{-- Modal Subsanar / Reanudar (RF-018) --}}
            <x-dialog-modal wire:model.live="modalReanudarAbierto" maxWidth="md">
                <x-slot name="title">
                    Registrar subsanación y reanudar
                    @if ($partidaModal) #{{ $partidaModal->numero_partida }} @endif
                </x-slot>

                <x-slot name="content">
                    <form wire:submit="ejecutarReanudacion" id="form-reanudar-partida" class="space-y-4">
                        @if ($partidaModal)
                            @php $devAbierta = $partidaModal->devoluciones->firstWhere('resultado_cierre', null); @endphp
                            @if ($devAbierta)
                                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                                    <div class="font-semibold">Observaciones de la devolución:</div>
                                    <div class="mt-1">{{ $devAbierta->observaciones }}</div>
                                </div>
                            @endif
                        @endif

                        <div>
                            <x-label for="resp_subsanacion" value="Respuesta de subsanación *" />
                            <textarea id="resp_subsanacion" rows="3" wire:model="respuestaSubsanacion"
                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                            <x-input-error for="respuestaSubsanacion" class="mt-1" />
                        </div>
                    </form>
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="cerrarModalReanudar">Cancelar</x-secondary-button>
                    <x-button class="ms-3" type="submit" form="form-reanudar-partida" wire:loading.attr="disabled">
                        Reanudar atención
                    </x-button>
                </x-slot>
            </x-dialog-modal>

            {{-- Modal Concluir / Entrega de materiales ADQ (RF-019) --}}
            <x-dialog-modal wire:model.live="modalConcluirAbierto" maxWidth="xl">
                <x-slot name="title">
                    {{ $esEntregaAdqModal ? 'Registrar entrega de materiales (ADQ)' : 'Concluir partida de servicio' }}
                    @if ($partidaModal) #{{ $partidaModal->numero_partida }} @endif
                </x-slot>

                <x-slot name="content">
                    <form wire:submit="ejecutarConclusion" id="form-concluir-partida" class="space-y-4">
                        @if ($esEntregaAdqModal)
                            <div class="rounded-lg border border-cyan-200 bg-cyan-50 p-3 text-xs text-cyan-900">
                                Partida de entrega de materiales de Adquisiciones (RF-017/RF-019): no requiere asignación operativa ni conformidad firmada y no concluye automáticamente la partida original.
                            </div>

                            <div>
                                <x-label for="recibe_material" value="Nombre de quien recibe los materiales *" />
                                <x-input id="recibe_material" type="text" class="mt-1 block w-full" wire:model="conclusionForm.nombre_quien_recibe_material" maxlength="255" />
                                <x-input-error for="conclusionForm.nombre_quien_recibe_material" class="mt-1" />
                            </div>

                            <div>
                                <x-label for="desc_entrega" value="Descripción de la entrega realizada *" />
                                <textarea id="desc_entrega" rows="3" wire:model="conclusionForm.descripcion_trabajo"
                                          class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                                <x-input-error for="conclusionForm.descripcion_trabajo" class="mt-1" />
                            </div>
                        @else
                            <div>
                                <x-label for="desc_trabajo" value="Descripción de los trabajos realizados *" />
                                <textarea id="desc_trabajo" rows="3" wire:model="conclusionForm.descripcion_trabajo"
                                          class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                                <x-input-error for="conclusionForm.descripcion_trabajo" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-label for="desc_materiales" value="Materiales utilizados (opcional)" />
                                    <x-input id="desc_materiales" type="text" class="mt-1 block w-full" wire:model="conclusionForm.descripcion_materiales" />
                                    <x-input-error for="conclusionForm.descripcion_materiales" class="mt-1" />
                                </div>
                                <div>
                                    <x-label for="cant_atendida" value="Cantidad atendida (opcional)" />
                                    <x-input id="cant_atendida" type="number" step="0.01" min="0" class="mt-1 block w-full" wire:model="conclusionForm.cantidad_atendida" />
                                    <x-input-error for="conclusionForm.cantidad_atendida" class="mt-1" />
                                </div>
                            </div>

                            <div class="pt-2 border-t border-slate-100">
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                    <x-checkbox wire:model.live="conclusionForm.conformidad_recibida" />
                                    <span class="font-medium">Se obtuvo conformidad firmada del área solicitante</span>
                                </label>
                            </div>

                            @if ($conclusionForm['conformidad_recibida'])
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <x-label for="recibe_nombre" value="Nombre de quien recibe / verifica *" />
                                        <x-input id="recibe_nombre" type="text" class="mt-1 block w-full" wire:model="conclusionForm.recibe_nombre" />
                                        <x-input-error for="conclusionForm.recibe_nombre" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-label for="recibe_cargo" value="Cargo *" />
                                        <x-input id="recibe_cargo" type="text" class="mt-1 block w-full" wire:model="conclusionForm.recibe_cargo" />
                                        <x-input-error for="conclusionForm.recibe_cargo" class="mt-1" />
                                    </div>
                                </div>

                                <div>
                                    <x-label for="archivo_conf" value="Documento firmado de conformidad (PDF, JPG o PNG, máx. 10 MB) *" />
                                    <input id="archivo_conf" type="file" accept=".pdf,.jpg,.jpeg,.png" wire:model="archivoConformidad"
                                           class="mt-1 block w-full text-xs text-slate-600 file:me-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-siger-50 file:text-siger-700" />
                                    <x-input-error for="archivoConformidad" class="mt-1" />
                                </div>
                            @else
                                <div>
                                    <x-label for="motivo_sin_conf" value="Motivo por el cual no se obtuvo conformidad *" />
                                    <textarea id="motivo_sin_conf" rows="2" wire:model="conclusionForm.motivo_sin_conformidad"
                                              class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                                    <x-input-error for="conclusionForm.motivo_sin_conformidad" class="mt-1" />
                                </div>
                            @endif

                            <div>
                                <x-label for="evidencias_files" :value="$conclusionForm['conformidad_recibida'] ? 'Evidencias fotográficas o documentales (opcional, PDF/JPG/PNG)' : 'Evidencias fotográficas o documentales (mínimo 1 archivo obligatorio, PDF/JPG/PNG) *'" />
                                <input id="evidencias_files" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" wire:model="evidenciasConclusion"
                                       class="mt-1 block w-full text-xs text-slate-600 file:me-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-siger-50 file:text-siger-700" />
                                <x-input-error for="evidenciasConclusion" class="mt-1" />
                            </div>
                        @endif
                    </form>
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="cerrarModalConcluir">Cancelar</x-secondary-button>
                    <x-button class="ms-3" type="submit" form="form-concluir-partida" wire:loading.attr="disabled">
                        {{ $esEntregaAdqModal ? 'Confirmar entrega' : 'Concluir partida' }}
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

            {{-- Modal Detalle e Historial Completo --}}
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
                        <div class="space-y-5 text-sm">
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

                            @if ($partidaModal->registroConclusion)
                                @php $reg = $partidaModal->registroConclusion; @endphp
                                <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-4 text-xs space-y-2">
                                    <div class="font-bold text-emerald-900">
                                        Conclusión registrada el {{ $reg->fecha_conclusion?->format('d/m/Y H:i') }} por {{ $reg->usuario?->name }}
                                    </div>
                                    <div class="text-slate-800">{{ $reg->descripcion_trabajo }}</div>
                                    @if ($partidaModal->nombre_quien_recibe_material)
                                        <div class="text-slate-700">Recibió material: <strong>{{ $partidaModal->nombre_quien_recibe_material }}</strong></div>
                                    @endif
                                    @if ($reg->conformidad_recibida)
                                        <div class="text-slate-700">
                                            Conformidad otorgada por: <strong>{{ $reg->recibe_nombre }}</strong> ({{ $reg->recibe_cargo }})
                                            @if ($reg->archivo_conformidad)
                                                · <a href="{{ route('partidas.conformidad.ver', $reg) }}" target="_blank" class="font-semibold text-siger-700 underline">Ver</a>
                                                · <a href="{{ route('partidas.conformidad.descargar', $reg) }}" class="font-semibold text-emerald-700 underline">Descargar</a>
                                            @endif
                                        </div>
                                    @elseif ($reg->motivo_sin_conformidad)
                                        <div class="text-amber-900">Sin conformidad: {{ $reg->motivo_sin_conformidad }}</div>
                                    @endif

                                    @if (is_array($reg->evidencias) && count($reg->evidencias) > 0)
                                        <div class="pt-1 space-y-1">
                                            <div class="font-semibold text-slate-700">Evidencias adjuntas:</div>
                                            @foreach ($reg->evidencias as $idx => $ev)
                                                <div class="flex items-center gap-3">
                                                    <span>{{ $ev['nombre_original'] ?? 'Evidencia #'.($idx + 1) }}</span>
                                                    <a href="{{ route('partidas.evidencias.ver', [$reg, $idx]) }}" target="_blank" class="font-semibold text-siger-700 underline">Ver</a>
                                                    <a href="{{ route('partidas.evidencias.descargar', [$reg, $idx]) }}" class="font-semibold text-emerald-700 underline">Descargar</a>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if ($partidaModal->estado?->clave === 'CANCELADO')
                                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-900 space-y-1">
                                    <div class="font-semibold">
                                        Cancelada por {{ $partidaModal->usuarioCancelacion?->name }} el {{ $partidaModal->fecha_cancelacion?->format('d/m/Y H:i') }}
                                    </div>
                                    <div>Motivo: {{ $partidaModal->motivo_cancelacion }}</div>
                                </div>
                            @endif

                            @if ($partidaModal->devoluciones->isNotEmpty())
                                <div>
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Historial de devoluciones</h4>
                                    <div class="space-y-2">
                                        @foreach ($partidaModal->devoluciones->sortByDesc('id') as $dev)
                                            <div class="border border-slate-200 rounded-lg p-3 text-xs space-y-1">
                                                <div class="flex items-center justify-between">
                                                    <span class="font-semibold text-slate-800">
                                                        Devuelta por {{ $dev->usuario?->name }} ({{ $dev->resultado_cierre ?? 'ABIERTA' }})
                                                    </span>
                                                    <span class="text-slate-400">{{ $dev->fecha_devolucion?->format('d/m/Y H:i') }}</span>
                                                </div>
                                                <div class="text-slate-700">Observaciones: {{ $dev->observaciones }}</div>
                                                @if ($dev->respuesta_subsanacion)
                                                    <div class="text-emerald-800">Subsanación ({{ $dev->usuarioReanudacion?->name }}): {{ $dev->respuesta_subsanacion }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (Auth::user()?->checkPermissionTo('solicitudes_materiales.ver', 'web'))
                                @php
                                    $solicitudesVisibles = $partidaModal->solicitudesMateriales
                                        ->filter(fn ($sol) => \Illuminate\Support\Facades\Gate::allows('view', $sol))
                                        ->sortByDesc('id');
                                @endphp
                                @if ($solicitudesVisibles->isNotEmpty() || \Illuminate\Support\Facades\Gate::allows('create', [\App\Models\SolicitudMaterial::class, $partidaModal]))
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Solicitudes de materiales</h4>
                                            <a href="{{ route('solicitudes-materiales.index', \Illuminate\Support\Facades\Gate::allows('create', [\App\Models\SolicitudMaterial::class, $partidaModal]) ? ['partida' => $partidaModal->id] : []) }}"
                                               class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                                                {{ \Illuminate\Support\Facades\Gate::allows('create', [\App\Models\SolicitudMaterial::class, $partidaModal]) ? '+ Solicitar materiales →' : 'Ver módulo de materiales →' }}
                                            </a>
                                        </div>

                                        @if ($solicitudesVisibles->isNotEmpty())
                                            <div class="space-y-2">
                                                @foreach ($solicitudesVisibles as $sol)
                                                    <div class="border border-slate-200 rounded-lg p-3 text-xs space-y-1">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="font-semibold text-slate-800">
                                                                Solicitud #{{ $sol->id }} · {{ $sol->estado }}
                                                            </span>
                                                            <span class="text-slate-400">{{ $sol->created_at?->format('d/m/Y H:i') }}</span>
                                                        </div>
                                                        <div class="text-slate-700">{{ $sol->descripcion }}</div>
                                                        @if ($sol->partidaGenerada)
                                                            <div class="text-emerald-800">
                                                                Partida ADQ #{{ $sol->partidaGenerada->numero_partida }} ·
                                                                <span class="font-mono">{{ $sol->partidaGenerada->folio?->folio ?? 'Pendiente de turnar' }}</span> ·
                                                                {{ $sol->partidaGenerada->estado?->nombre }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
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
