<div>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Solicitudes de materiales') }}
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Gestión formal de requerimientos de materiales ante Dirección y seguimiento de partidas generadas para Adquisiciones (RF-017).
                </p>
            </div>

            @if ($puedeCrear)
                <x-button type="button" wire:click="abrirCrear">
                    + Nueva solicitud
                </x-button>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Indicadores rápidos --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Solicitudes visibles</p>
                    <p class="mt-1 text-2xl font-extrabold text-siger-900">{{ number_format($resumen['total']) }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pendientes de Dirección</p>
                    <p class="mt-1 text-2xl font-extrabold text-cyan-700">{{ number_format($resumen['enviadas']) }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Borrador / Aclaración</p>
                    <p class="mt-1 text-2xl font-extrabold text-amber-600">{{ number_format($resumen['borradores_aclaracion']) }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Autorizadas</p>
                    <p class="mt-1 text-2xl font-extrabold text-emerald-700">{{ number_format($resumen['autorizadas']) }}</p>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        {{ number_format($resumen['adq_concluidas']) }} entregadas ADQ · {{ number_format($resumen['rechazadas']) }} rechazadas
                    </p>
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
                <div class="p-4 sm:p-6 border-b border-slate-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <x-input type="search"
                             wire:model.live.debounce.300ms="buscar"
                             placeholder="Descripción, justificación, folio u oficio..."
                             class="w-full text-sm lg:col-span-2" />

                    <select wire:model.live="estadoFiltro"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">Todos los estados</option>
                        @if ($esJefe)
                            <option value="BORRADOR">Borrador</option>
                        @endif
                        <option value="ENVIADA">Enviada</option>
                        <option value="INFORMACION_REQUERIDA">Información requerida</option>
                        <option value="AUTORIZADA">Autorizada</option>
                        <option value="RECHAZADA">Rechazada</option>
                    </select>

                    @if ($esDireccion)
                        <select wire:model.live="departamentoFiltro"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="">Todos los departamentos</option>
                            @foreach ($departamentos as $depto)
                                <option value="{{ $depto->id }}">{{ $depto->siglas }} — {{ $depto->nombre }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Solicitud / Partida origen</th>
                                <th class="px-6 py-3">Materiales requeridos</th>
                                <th class="px-6 py-3">Estado</th>
                                <th class="px-6 py-3">Seguimiento ADQ</th>
                                <th class="px-6 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($solicitudes as $sol)
                                @php
                                    $puedeEditar = \Illuminate\Support\Facades\Gate::allows('update', $sol);
                                    $puedeEnviar = \Illuminate\Support\Facades\Gate::allows('enviar', $sol);
                                    $puedeSolicitarInfo = \Illuminate\Support\Facades\Gate::allows('solicitarInformacion', $sol);
                                    $puedeAutorizar = \Illuminate\Support\Facades\Gate::allows('autorizar', $sol);
                                    $puedeRechazar = \Illuminate\Support\Facades\Gate::allows('rechazar', $sol);
                                    $etiquetaEstado = match ($sol->estado) {
                                        'BORRADOR' => 'Borrador',
                                        'ENVIADA' => 'Enviada',
                                        'INFORMACION_REQUERIDA' => 'Información requerida',
                                        'AUTORIZADA' => 'Autorizada',
                                        'RECHAZADA' => 'Rechazada',
                                        default => $sol->estado,
                                    };
                                @endphp
                                <tr wire:key="sol-mat-row-{{ $sol->id }}" class="hover:bg-slate-50/70">
                                    <td class="px-6 py-3.5 whitespace-nowrap">
                                        <div class="font-semibold text-siger-800">
                                            Solicitud #{{ $sol->id }}
                                        </div>
                                        <div class="text-xs text-slate-600">
                                            Exp. #{{ $sol->partidaOriginal?->servicio_id }} · Partida #{{ $sol->partidaOriginal?->numero_partida }}
                                            @if ($sol->partidaOriginal?->departamento)
                                                <span class="ms-1 font-mono font-semibold px-1.5 py-0.5 rounded bg-slate-100 text-slate-700">
                                                    {{ $sol->partidaOriginal->departamento->siglas }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] font-mono text-cyan-800 mt-0.5">
                                            {{ $sol->partidaOriginal?->folio?->folio ?? 'Sin folio' }}
                                        </div>
                                        <div class="text-[11px] text-slate-400">
                                            Solicitó: {{ $sol->solicitante?->name }}
                                        </div>
                                    </td>

                                    <td class="px-6 py-3.5 max-w-md">
                                        <div class="font-medium text-slate-800 line-clamp-2">{{ $sol->descripcion }}</div>
                                        @if ($sol->cantidad !== null)
                                            <div class="text-xs text-slate-500 mt-0.5">
                                                Cantidad: <span class="font-semibold">{{ rtrim(rtrim((string) $sol->cantidad, '0'), '.') }}</span> {{ $sol->unidad_medida }}
                                            </div>
                                        @endif
                                        @if ($sol->estado === 'INFORMACION_REQUERIDA' && $sol->observaciones_direccion)
                                            <div class="text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded px-2 py-1 mt-1 line-clamp-2">
                                                <strong>Dirección:</strong> {{ $sol->observaciones_direccion }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="px-6 py-3.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ match ($sol->estado) {
                                            'AUTORIZADA' => 'bg-emerald-100 text-emerald-800',
                                            'RECHAZADA' => 'bg-red-100 text-red-800',
                                            'INFORMACION_REQUERIDA' => 'bg-amber-100 text-amber-800',
                                            'ENVIADA' => 'bg-cyan-100 text-cyan-800',
                                            default => 'bg-slate-100 text-slate-700',
                                        } }}">
                                            {{ $etiquetaEstado }}
                                        </span>
                                        @if ($sol->fecha_envio)
                                            <div class="text-[11px] text-slate-400 mt-1">
                                                Enviada: {{ $sol->fecha_envio->format('d/m/Y H:i') }}
                                            </div>
                                        @endif
                                        @if ($sol->estado === 'ENVIADA' && $sol->partidaOriginal?->estado?->clave !== 'EN_ATENCION')
                                            <div class="text-[11px] font-semibold text-amber-700 mt-1">
                                                En pausa: partida origen {{ $sol->partidaOriginal?->estado?->nombre }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="px-6 py-3.5 whitespace-nowrap text-xs">
                                        @if ($sol->partidaGenerada)
                                            <div class="font-semibold text-slate-800">
                                                Partida #{{ $sol->partidaGenerada->numero_partida }} (ADQ)
                                            </div>
                                            <div class="font-mono text-[11px] text-cyan-800">
                                                {{ $sol->partidaGenerada->folio?->folio ?? 'Pendiente de turnar' }}
                                            </div>
                                            <div class="mt-0.5">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold {{ $sol->partidaGenerada->estado?->clave === 'CONCLUIDO' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                                    {{ $sol->partidaGenerada->estado?->nombre }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-3.5 text-right">
                                        <div class="flex flex-wrap justify-end gap-x-2.5 gap-y-1">
                                            <button type="button" wire:click="abrirDetalle({{ $sol->id }})" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
                                                Detalle
                                            </button>

                                            @if ($puedeEditar)
                                                <button type="button" wire:click="abrirEditar({{ $sol->id }})" class="text-xs font-semibold text-indigo-700 hover:text-indigo-900">
                                                    Editar
                                                </button>
                                            @endif

                                            @if ($puedeEnviar)
                                                <button type="button" wire:click="abrirEnviar({{ $sol->id }})" class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                                                    {{ $sol->estado === 'INFORMACION_REQUERIDA' ? 'Responder' : 'Enviar' }}
                                                </button>
                                            @endif

                                            @if ($puedeSolicitarInfo)
                                                <button type="button" wire:click="abrirSolicitarInformacion({{ $sol->id }})" class="text-xs font-semibold text-amber-700 hover:text-amber-900">
                                                    Pedir info
                                                </button>
                                            @endif

                                            @if ($puedeAutorizar)
                                                <button type="button" wire:click="abrirAutorizar({{ $sol->id }})" class="text-xs font-semibold text-emerald-700 hover:text-emerald-900">
                                                    Autorizar
                                                </button>
                                            @endif

                                            @if ($puedeRechazar)
                                                <button type="button" wire:click="abrirRechazar({{ $sol->id }})" class="text-xs font-semibold text-red-700 hover:text-red-900">
                                                    Rechazar
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                                        No se encontraron solicitudes de materiales con los criterios seleccionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($solicitudes->hasPages())
                    <div class="px-6 py-4 border-t border-slate-100">
                        {{ $solicitudes->links() }}
                    </div>
                @endif
            </div>

            {{-- Modal Crear / Editar solicitud (Jefe) --}}
            <x-dialog-modal wire:model.live="modalFormularioAbierto" maxWidth="xl">
                <x-slot name="title">
                    {{ $solicitudEditandoId ? 'Editar solicitud de materiales #'.$solicitudEditandoId : 'Nueva solicitud de materiales' }}
                </x-slot>

                <x-slot name="content">
                    <form wire:submit="guardarSolicitud" id="form-solicitud-material" class="space-y-4">
                        @if ($solicitudEditandoId === null)
                            <div>
                                <x-label for="partida_original_id" value="Partida en atención *" />
                                <select id="partida_original_id" wire:model="partidaOriginalId"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                    <option value="">Selecciona una partida En atención de tu departamento...</option>
                                    @foreach ($partidasElegibles as $p)
                                        <option value="{{ $p->id }}">
                                            Exp. #{{ $p->servicio_id }} · Partida #{{ $p->numero_partida }} ({{ $p->folio?->folio ?? 'Sin folio' }}) — {{ \Illuminate\Support\Str::limit($p->concepto, 65) }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error for="partidaOriginalId" class="mt-1" />
                                @if ($partidasElegibles->isEmpty())
                                    <p class="text-xs text-amber-700 mt-1">
                                        No tienes partidas en estado <strong>En atención</strong> en tu departamento para solicitar materiales.
                                    </p>
                                @endif
                            </div>
                        @elseif ($solicitudModal)
                            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-xs text-slate-600">
                                <div class="font-semibold text-slate-800">
                                    Exp. #{{ $solicitudModal->partidaOriginal?->servicio_id }} · Partida #{{ $solicitudModal->partidaOriginal?->numero_partida }}
                                    ({{ $solicitudModal->partidaOriginal?->folio?->folio ?? 'Sin folio' }})
                                </div>
                                <div class="mt-0.5">{{ $solicitudModal->partidaOriginal?->concepto }}</div>
                            </div>

                            @if ($solicitudModal->estado === 'INFORMACION_REQUERIDA' && $solicitudModal->observaciones_direccion)
                                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                                    <div class="font-semibold">Observaciones de Dirección:</div>
                                    <div class="mt-1">{{ $solicitudModal->observaciones_direccion }}</div>
                                </div>
                            @endif
                        @endif

                        <div>
                            <x-label for="sol_descripcion" value="Descripción de materiales, insumos o recursos requeridos *" />
                            <textarea id="sol_descripcion" rows="3" wire:model="form.descripcion"
                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                            <x-input-error for="form.descripcion" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label for="sol_cantidad" value="Cantidad (opcional)" />
                                <x-input id="sol_cantidad" type="number" step="0.01" min="0.01" class="mt-1 block w-full" wire:model="form.cantidad" />
                                <x-input-error for="form.cantidad" class="mt-1" />
                            </div>
                            <div>
                                <x-label for="sol_unidad" value="Unidad de medida (obligatoria si capturas cantidad)" />
                                <x-input id="sol_unidad" type="text" maxlength="100" class="mt-1 block w-full" wire:model="form.unidad_medida" placeholder="Ej. pieza, caja, metro, litro" />
                                <x-input-error for="form.unidad_medida" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-label for="sol_justificacion" value="Justificación operativa *" />
                            <textarea id="sol_justificacion" rows="3" wire:model="form.justificacion"
                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                            <x-input-error for="form.justificacion" class="mt-1" />
                        </div>
                    </form>
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="cerrarModalFormulario">Cancelar</x-secondary-button>
                    <x-button class="ms-3" type="submit" form="form-solicitud-material" wire:loading.attr="disabled">
                        {{ $solicitudEditandoId ? 'Guardar cambios' : 'Guardar borrador' }}
                    </x-button>
                </x-slot>
            </x-dialog-modal>

            {{-- Modal Enviar / Responder aclaración (Jefe) --}}
            <x-dialog-modal wire:model.live="modalEnviarAbierto" maxWidth="lg">
                <x-slot name="title">
                    @if ($solicitudModal && $solicitudModal->estado === 'INFORMACION_REQUERIDA')
                        Responder observaciones y reenviar — Solicitud #{{ $solicitudModal->id }}
                    @else
                        Enviar solicitud a Dirección @if ($solicitudModal) #{{ $solicitudModal->id }} @endif
                    @endif
                </x-slot>

                <x-slot name="content">
                    <form wire:submit="ejecutarEnvio" id="form-enviar-solicitud" class="space-y-4">
                        @if ($solicitudModal)
                            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-xs text-slate-700 space-y-1">
                                <div class="font-semibold text-slate-800">{{ $solicitudModal->descripcion }}</div>
                                <div>Justificación: {{ $solicitudModal->justificacion }}</div>
                            </div>

                            @if ($solicitudModal->estado === 'INFORMACION_REQUERIDA')
                                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                                    <div class="font-semibold">Información solicitada por Dirección:</div>
                                    <div class="mt-1">{{ $solicitudModal->observaciones_direccion }}</div>
                                </div>

                                <div>
                                    <x-label for="respuesta_aclaracion" value="Respuesta de aclaración *" />
                                    <textarea id="respuesta_aclaracion" rows="3" wire:model="respuestaAclaracion"
                                              class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                                    <x-input-error for="respuestaAclaracion" class="mt-1" />
                                </div>
                            @else
                                <p class="text-xs text-slate-600">
                                    Al enviarla, Dirección recibirá la solicitud para su revisión y resolución. Mientras esté en revisión no podrás editarla salvo que Dirección solicite aclaraciones.
                                </p>
                            @endif
                        @endif
                    </form>
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="cerrarModalEnviar">Cancelar</x-secondary-button>
                    <x-button class="ms-3" type="submit" form="form-enviar-solicitud" wire:loading.attr="disabled">
                        {{ $solicitudModal && $solicitudModal->estado === 'INFORMACION_REQUERIDA' ? 'Enviar respuesta' : 'Enviar a Dirección' }}
                    </x-button>
                </x-slot>
            </x-dialog-modal>

            {{-- Modal Solicitar información complementaria (Dirección) --}}
            <x-dialog-modal wire:model.live="modalSolicitarInfoAbierto" maxWidth="md">
                <x-slot name="title">
                    Solicitar información complementaria @if ($solicitudModal) — Solicitud #{{ $solicitudModal->id }} @endif
                </x-slot>

                <x-slot name="content">
                    <form wire:submit="ejecutarSolicitudInformacion" id="form-solicitar-info" class="space-y-4">
                        <p class="text-xs text-slate-600">
                            La solicitud regresará al Jefe solicitante en estado <strong>Información requerida</strong> para que aclare o ajuste los datos y la reenvíe.
                        </p>
                        <div>
                            <x-label for="obs_direccion_info" value="Observaciones / información requerida *" />
                            <textarea id="obs_direccion_info" rows="3" wire:model="observacionesDireccion"
                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                            <x-input-error for="observacionesDireccion" class="mt-1" />
                        </div>
                    </form>
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="cerrarModalSolicitarInfo">Cancelar</x-secondary-button>
                    <x-button class="ms-3" type="submit" form="form-solicitar-info" wire:loading.attr="disabled">
                        Solicitar información
                    </x-button>
                </x-slot>
            </x-dialog-modal>

            {{-- Modal Rechazar solicitud (Dirección) --}}
            <x-dialog-modal wire:model.live="modalRechazarAbierto" maxWidth="md">
                <x-slot name="title">
                    Rechazar solicitud de materiales @if ($solicitudModal) #{{ $solicitudModal->id }} @endif
                </x-slot>

                <x-slot name="content">
                    <form wire:submit="ejecutarRechazo" id="form-rechazar-solicitud" class="space-y-4">
                        <p class="text-xs text-slate-600">
                            El rechazo es definitivo. No se creará partida para Adquisiciones y la partida original permanecerá en <strong>En atención</strong>.
                        </p>
                        <div>
                            <x-label for="motivo_rechazo" value="Motivo del rechazo *" />
                            <textarea id="motivo_rechazo" rows="3" wire:model="motivoRechazo"
                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                            <x-input-error for="motivoRechazo" class="mt-1" />
                        </div>
                    </form>
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="cerrarModalRechazar">Cancelar</x-secondary-button>
                    <x-danger-button class="ms-3" type="submit" form="form-rechazar-solicitud" wire:loading.attr="disabled">
                        Confirmar rechazo
                    </x-danger-button>
                </x-slot>
            </x-dialog-modal>

            {{-- Modal Autorizar y generar partida ADQ (Dirección) --}}
            <x-dialog-modal wire:model.live="modalAutorizarAbierto" maxWidth="xl">
                <x-slot name="title">
                    Autorizar solicitud @if ($solicitudModal) #{{ $solicitudModal->id }} @endif y generar partida ADQ
                </x-slot>

                <x-slot name="content">
                    <form wire:submit="ejecutarAutorizacion" id="form-autorizar-solicitud" class="space-y-4">
                        <div class="rounded-lg border border-cyan-200 bg-cyan-50 p-3 text-xs text-cyan-900">
                            Se creará una partida adicional en estado <strong>Registrado</strong> dirigida a <strong>Adquisiciones (ADQ)</strong> dentro del mismo expediente (sin folio todavía). Después podrás turnarla formalmente en el módulo de Partidas. La partida original permanecerá en <strong>En atención</strong>.
                        </div>

                        <div>
                            <x-label for="aut_concepto" value="Concepto de la nueva partida para Adquisiciones *" />
                            <textarea id="aut_concepto" rows="3" wire:model="autorizacionForm.concepto"
                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                            <x-input-error for="autorizacionForm.concepto" class="mt-1" />
                        </div>

                        <div>
                            <x-label for="aut_tipo" value="Tipo de servicio *" />
                            <select id="aut_tipo" wire:model.live="autorizacionForm.tipo_servicio_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                <option value="">Selecciona el tipo de servicio...</option>
                                @foreach ($tiposServicioActivos as $tipo)
                                    <option value="{{ $tipo->id }}">
                                        {{ $tipo->descripcion }} @if ($tipo->requiere_cantidad) (requiere cantidad) @endif
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error for="autorizacionForm.tipo_servicio_id" class="mt-1" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label for="aut_cantidad" :value="$tipoSeleccionadoAutorizar?->requiere_cantidad ? 'Cantidad solicitada *' : 'Cantidad solicitada (opcional)'" />
                                <x-input id="aut_cantidad" type="number" step="0.01" min="0.01" class="mt-1 block w-full" wire:model="autorizacionForm.cantidad_solicitada" />
                                <x-input-error for="autorizacionForm.cantidad_solicitada" class="mt-1" />
                            </div>
                            <div>
                                <x-label for="aut_unidad" :value="$tipoSeleccionadoAutorizar?->requiere_cantidad ? 'Unidad de medida *' : 'Unidad de medida (opcional)'" />
                                <x-input id="aut_unidad" type="text" maxlength="100" class="mt-1 block w-full" wire:model="autorizacionForm.unidad_medida" />
                                <x-input-error for="autorizacionForm.unidad_medida" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-label for="aut_obs" value="Observaciones de Dirección (opcional)" />
                            <textarea id="aut_obs" rows="2" wire:model="autorizacionForm.observaciones_direccion"
                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
                            <x-input-error for="autorizacionForm.observaciones_direccion" class="mt-1" />
                        </div>
                    </form>
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="cerrarModalAutorizar">Cancelar</x-secondary-button>
                    <x-button class="ms-3" type="submit" form="form-autorizar-solicitud" wire:loading.attr="disabled">
                        Autorizar y crear partida ADQ
                    </x-button>
                </x-slot>
            </x-dialog-modal>

            {{-- Modal Detalle, Seguimiento ADQ e Historial --}}
            <x-dialog-modal wire:model.live="modalDetalleAbierto" maxWidth="2xl">
                <x-slot name="title">
                    @if ($solicitudModal)
                        Solicitud de materiales #{{ $solicitudModal->id }}
                    @else
                        Detalle de solicitud
                    @endif
                </x-slot>

                <x-slot name="content">
                    @if ($solicitudModal)
                        <div class="space-y-5 text-sm">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-slate-50 border border-slate-200 rounded-lg p-4">
                                <div>
                                    <div class="text-xs text-slate-400 font-semibold uppercase">Partida original</div>
                                    <div class="font-semibold text-slate-800">
                                        Exp. #{{ $solicitudModal->partidaOriginal?->servicio_id }} · Partida #{{ $solicitudModal->partidaOriginal?->numero_partida }}
                                    </div>
                                    <div class="text-xs font-mono text-cyan-800">
                                        {{ $solicitudModal->partidaOriginal?->departamento?->siglas }} · {{ $solicitudModal->partidaOriginal?->folio?->folio ?? 'Sin folio' }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        Estado actual: {{ $solicitudModal->partidaOriginal?->estado?->nombre }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-slate-400 font-semibold uppercase">Estado de la solicitud</div>
                                    <div class="font-semibold text-slate-800">{{ $solicitudModal->estado }}</div>
                                    <div class="text-xs text-slate-500">Solicitó: {{ $solicitudModal->solicitante?->name }}</div>
                                    @if ($solicitudModal->resolutor)
                                        <div class="text-xs text-slate-500">
                                            Resolvió: {{ $solicitudModal->resolutor->name }} ({{ $solicitudModal->fecha_resolucion?->format('d/m/Y H:i') }})
                                        </div>
                                    @endif
                                </div>
                                <div class="sm:col-span-2">
                                    <div class="text-xs text-slate-400 font-semibold uppercase">Materiales requeridos</div>
                                    <div class="text-slate-800">{{ $solicitudModal->descripcion }}</div>
                                    @if ($solicitudModal->cantidad !== null)
                                        <div class="text-xs text-slate-600 mt-1">
                                            Cantidad: <strong>{{ rtrim(rtrim((string) $solicitudModal->cantidad, '0'), '.') }} {{ $solicitudModal->unidad_medida }}</strong>
                                        </div>
                                    @endif
                                </div>
                                <div class="sm:col-span-2">
                                    <div class="text-xs text-slate-400 font-semibold uppercase">Justificación</div>
                                    <div class="text-slate-700">{{ $solicitudModal->justificacion }}</div>
                                </div>
                                @if ($solicitudModal->observaciones_direccion)
                                    <div class="sm:col-span-2">
                                        <div class="text-xs text-slate-400 font-semibold uppercase">Observaciones de Dirección</div>
                                        <div class="text-slate-700">{{ $solicitudModal->observaciones_direccion }}</div>
                                    </div>
                                @endif
                            </div>

                            {{-- Resumen de seguimiento de la partida ADQ derivada (visible también para el Jefe solicitante sin abrir acceso general a ADQ) --}}
                            @if ($solicitudModal->partidaGenerada)
                                @php $adq = $solicitudModal->partidaGenerada; @endphp
                                <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-4 text-xs space-y-1.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-bold text-emerald-900">
                                            Seguimiento de partida generada para Adquisiciones (ADQ)
                                        </span>
                                        <span class="px-2 py-0.5 rounded-full font-semibold {{ $adq->estado?->clave === 'CONCLUIDO' ? 'bg-emerald-200 text-emerald-900' : 'bg-white text-slate-700 border border-slate-200' }}">
                                            {{ $adq->estado?->nombre }}
                                        </span>
                                    </div>
                                    <div class="text-slate-700">
                                        Partida <strong>#{{ $adq->numero_partida }}</strong> (Exp. #{{ $adq->servicio_id }}) ·
                                        Folio ADQ: <span class="font-mono font-semibold text-cyan-900">{{ $adq->folio?->folio ?? 'Pendiente de turnar a ADQ' }}</span>
                                    </div>
                                    <div class="text-slate-700">
                                        Conclusión / Entrega:
                                        <strong>{{ $adq->fecha_conclusion ? $adq->fecha_conclusion->format('d/m/Y H:i') : 'En trámite' }}</strong>
                                        @if ($adq->nombre_quien_recibe_material)
                                            · Recibió: <strong>{{ $adq->nombre_quien_recibe_material }}</strong>
                                        @endif
                                    </div>
                                    @if ($esDireccion && $adq->servicio_folio_id === null)
                                        <div class="pt-1">
                                            <a href="{{ route('partidas.index') }}" class="font-semibold text-siger-700 hover:text-siger-900 underline">
                                                Ir a Partidas para turnar formalmente a ADQ →
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Historial funcional de la solicitud --}}
                            @if (\Illuminate\Support\Facades\Gate::allows('viewHistory', $solicitudModal))
                                <div>
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Historial de movimientos</h4>
                                    <div class="space-y-2">
                                        @forelse ($solicitudModal->historial->sortByDesc('id') as $mov)
                                            <div class="border border-slate-200 rounded-lg p-3 text-xs space-y-1">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-semibold text-slate-800">
                                                        {{ str_replace('_', ' ', $mov->accion) }}
                                                        @if ($mov->estado_anterior)
                                                            ({{ $mov->estado_anterior }} → {{ $mov->estado_nuevo }})
                                                        @else
                                                            ({{ $mov->estado_nuevo }})
                                                        @endif
                                                    </span>
                                                    <span class="text-slate-400">{{ $mov->fecha?->format('d/m/Y H:i') }}</span>
                                                </div>
                                                <div class="text-slate-500">Usuario: {{ $mov->usuario?->name }}</div>
                                                @if ($mov->observaciones)
                                                    <div class="text-slate-700">{{ $mov->observaciones }}</div>
                                                @endif
                                            </div>
                                        @empty
                                            <p class="text-xs text-slate-400">Sin movimientos registrados.</p>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
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
