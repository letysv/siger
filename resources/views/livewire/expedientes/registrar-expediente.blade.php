<div>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Registrar expediente') }}
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Recepción formal de oficio con creación automática de la partida #1.
                </p>
            </div>

            <a href="{{ route('expedientes.index') }}" class="text-xs font-semibold text-siger-700 hover:text-siger-900">
                ← Volver al listado
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
<form wire:submit="guardar" class="space-y-6">
    @if ($posiblesDuplicados->isNotEmpty())
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 space-y-2">
            <div class="font-semibold">
                Aviso de posible duplicado: ya existen expedientes con este mismo número de oficio, área y ejercicio:
            </div>
            <ul class="list-disc ps-5 text-xs space-y-1">
                @foreach ($posiblesDuplicados as $duplicado)
                    <li>
                        Expediente #{{ $duplicado->id }} ({{ $duplicado->numero_oficio }}) —
                        {{ $duplicado->asunto }} —
                        Estado: {{ $duplicado->estado?->nombre }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Sección 1: Datos del oficio --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
        <div class="border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-800">Datos del oficio y recepción</h3>
            <p class="text-xs text-slate-500">El folio departamental no se genera en la recepción, sino hasta el primer turnado formal.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <x-label for="numero_oficio" value="Número de oficio" />
                <x-input id="numero_oficio" type="text" class="mt-1 block w-full" wire:model.live.debounce.400ms="form.numero_oficio" maxlength="100" />
                <x-input-error for="form.numero_oficio" class="mt-1" />
            </div>

            <div>
                <x-label for="fecha_oficio" value="Fecha del oficio" />
                <x-input id="fecha_oficio" type="date" class="mt-1 block w-full" wire:model="form.fecha_oficio" />
                <x-input-error for="form.fecha_oficio" class="mt-1" />
            </div>

            <div>
                <x-label for="fecha_recepcion" value="Fecha y hora de recepción *" />
                <x-input id="fecha_recepcion" type="datetime-local" class="mt-1 block w-full" wire:model.live="form.fecha_recepcion" />
                <x-input-error for="form.fecha_recepcion" class="mt-1" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-label for="area_solicitante_id" value="Área solicitante *" />
                <select id="area_solicitante_id" wire:model.live="form.area_solicitante_id"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                    <option value="">Selecciona un área...</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->id }}">{{ $area->clave }} — {{ $area->nombre }}</option>
                    @endforeach
                </select>
                <x-input-error for="form.area_solicitante_id" class="mt-1" />
            </div>

            <div>
                <x-label for="nombre_solicitante" value="Nombre del solicitante *" />
                <x-input id="nombre_solicitante" type="text" class="mt-1 block w-full" wire:model="form.nombre_solicitante" maxlength="255" />
                <x-input-error for="form.nombre_solicitante" class="mt-1" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-label for="dirigido_a" value="Dirigido a *" />
                <x-input id="dirigido_a" type="text" class="mt-1 block w-full" wire:model="form.dirigido_a" maxlength="255" />
                <x-input-error for="form.dirigido_a" class="mt-1" />
            </div>

            <div class="flex items-end pb-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                    <x-checkbox wire:model.live="form.es_via_rapida" />
                    <span class="font-medium">Canalización por vía rápida</span>
                </label>
            </div>
        </div>

        <div>
            <x-label for="asunto" value="Asunto *" />
            <x-input id="asunto" type="text" class="mt-1 block w-full" wire:model="form.asunto" maxlength="500" />
            <x-input-error for="form.asunto" class="mt-1" />
        </div>

        <div>
            <x-label for="descripcion" value="Descripción adicional del oficio" />
            <textarea id="descripcion" rows="3" wire:model="form.descripcion"
                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
            <x-input-error for="form.descripcion" class="mt-1" />
        </div>
    </div>

    {{-- Sección 2: Partida inicial (#1) --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
        <div class="border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-800">Partida inicial (Partida #1)</h3>
            <p class="text-xs text-slate-500">Todo expediente inicia automáticamente con su primera partida en estado Registrado.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-label for="tipo_servicio_id" value="Tipo de servicio" />
                <select id="tipo_servicio_id" wire:model.live="form.tipo_servicio_id"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                    <option value="">Por definir</option>
                    @foreach ($tiposServicio as $tipo)
                        <option value="{{ $tipo->id }}">{{ $tipo->descripcion }}</option>
                    @endforeach
                </select>
                <x-input-error for="form.tipo_servicio_id" class="mt-1" />
            </div>

            <div>
                <x-label for="departamento_id" :value="$form['es_via_rapida'] ? 'Departamento propuesto *' : 'Departamento propuesto (opcional)'" />
                <select id="departamento_id"
                        wire:model="form.departamento_id"
                        @disabled($form['es_via_rapida'] && ! $esGeneral && $departamentoUsuarioId !== null)
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm disabled:bg-slate-100">
                    <option value="">Sin departamento asignado aún</option>
                    @foreach ($departamentos as $depto)
                        <option value="{{ $depto->id }}">{{ $depto->siglas }} — {{ $depto->nombre }}</option>
                    @endforeach
                </select>
                <x-input-error for="form.departamento_id" class="mt-1" />
            </div>
        </div>

        <div>
            <x-label for="concepto" value="Concepto específico de la partida (si se omite, usará el asunto)" />
            <textarea id="concepto" rows="2" wire:model="form.concepto"
                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
            <x-input-error for="form.concepto" class="mt-1" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-label for="cantidad_solicitada" :value="$requiereCantidad ? 'Cantidad solicitada *' : 'Cantidad solicitada'" />
                <x-input id="cantidad_solicitada" type="number" step="0.01" min="0.01" class="mt-1 block w-full" wire:model="form.cantidad_solicitada" />
                <x-input-error for="form.cantidad_solicitada" class="mt-1" />
            </div>

            <div>
                <x-label for="unidad_medida" value="Unidad de medida" />
                <x-input id="unidad_medida" type="text" class="mt-1 block w-full" wire:model="form.unidad_medida" maxlength="100" placeholder="Ej. Pieza, Servicio, Caja..." />
                <x-input-error for="form.unidad_medida" class="mt-1" />
            </div>
        </div>

        <div>
            <x-label for="observaciones" value="Observaciones de la partida" />
            <textarea id="observaciones" rows="2" wire:model="form.observaciones"
                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"></textarea>
            <x-input-error for="form.observaciones" class="mt-1" />
        </div>
    </div>

    {{-- Sección 3: Documentos adjuntos --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
        <div class="border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-800">Documentos del expediente (opcional)</h3>
            <p class="text-xs text-slate-500">Se almacenan en repositorio privado y solo pueden consultarse según los permisos y alcance del usuario.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <x-label for="archivos" value="Digitalizado del oficio / anexos (PDF, JPG, PNG, DOC/DOCX, máx. 10 MB c/u)" />
                <input id="archivos" type="file" multiple wire:model="archivos"
                       class="mt-2 block w-full text-sm text-slate-600 file:me-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-siger-50 file:text-siger-700 hover:file:bg-siger-100" />
                <x-input-error for="archivos" class="mt-1" />
                <x-input-error for="archivos.*" class="mt-1" />
            </div>

            <div>
                <x-label for="acuse" value="Acuse de recibo inicial (PDF, JPG, PNG, DOC/DOCX, máx. 10 MB)" />
                <input id="acuse" type="file" wire:model="acuse"
                       class="mt-2 block w-full text-sm text-slate-600 file:me-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-siger-50 file:text-siger-700 hover:file:bg-siger-100" />
                <x-input-error for="acuse" class="mt-1" />
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('expedientes.index') }}"
           class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
            Cancelar
        </a>

        <x-button type="submit" wire:loading.attr="disabled">
            Registrar expediente
        </x-button>
    </div>
</form>
        </div>
    </div>
</div>
