@php
    $usuario = Auth::user();
    $roles = $usuario?->getRoleNames() ?? collect();
    $departamento = $usuario?->departamento;

    $indicadores = [
        [
            'etiqueta' => 'Expedientes registrados',
            'valor' => \App\Models\Servicio::count(),
            'detalle' => 'Oficios en el sistema',
        ],
        [
            'etiqueta' => 'Partidas de servicio',
            'valor' => \App\Models\Partida::count(),
            'detalle' => 'Partidas generadas',
        ],
        [
            'etiqueta' => 'Solicitudes registradas',
            'valor' => \App\Models\SolicitudMaterial::count(),
            'detalle' => 'Requerimientos de insumos',
        ],
        [
            'etiqueta' => 'Departamentos activos',
            'valor' => \App\Models\Departamento::where('activo', true)->count(),
            'detalle' => 'Áreas operativas habilitadas',
        ],
    ];

    $modulos = collect(\App\Support\MenuSiger::principal($usuario))
        ->reject(fn (array $item): bool => ($item['ruta'] ?? null) === 'dashboard')
        ->merge(\App\Support\MenuSiger::administracion($usuario))
        ->values();
@endphp

<div class="space-y-8">
    {{-- Banner institucional --}}
    <div class="bg-gradient-to-br from-siger-700 via-siger-800 to-siger-900 rounded-2xl shadow-lg p-8 text-white relative overflow-hidden">
        <div class="absolute -top-10 -right-10 w-44 h-44 bg-white/5 rounded-full blur-2xl"></div>
        <div class="absolute -bottom-14 -left-14 w-56 h-56 bg-white/5 rounded-full blur-3xl"></div>

        <div class="relative z-10">
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">
                Bienvenido(a), {{ $usuario?->name ?? __('Usuario') }}
            </h1>
            <p class="text-siger-200 mt-2 text-sm md:text-base max-w-2xl">
                SIGER — Sistema Institucional de Gestión de Expedientes y Requerimientos
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-2.5">
                <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm text-xs font-semibold px-3 py-1.5 rounded-full border border-white/20">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Rol: {{ $roles->isNotEmpty() ? $roles->join(', ') : 'Sin rol asignado' }}</span>
                </div>

                <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-sm text-xs font-semibold px-3 py-1.5 rounded-full border border-white/20">
                    <span>{{ $departamento ? $departamento->nombre . ' (' . $departamento->siglas . ')' : 'Alcance general' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Indicadores rápidos --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($indicadores as $indicador)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {{ $indicador['etiqueta'] }}
                </p>
                <p class="mt-2 text-3xl font-extrabold text-siger-900">
                    {{ number_format($indicador['valor']) }}
                </p>
                <p class="mt-1 text-xs text-slate-400">
                    {{ $indicador['detalle'] }}
                </p>
            </div>
        @endforeach
    </div>

    {{-- Cuadrícula de módulos --}}
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">
                Módulos del sistema
            </h2>
            <span class="text-xs text-slate-400">
                Visibilidad según permisos y rol asignado
            </span>
        </div>

        @if ($modulos->isEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-dashed border-slate-300 p-6 text-sm text-slate-600">
                Tu cuenta no tiene módulos habilitados todavía. Solicita al administrador la asignación de rol y departamento correspondientes.
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($modulos as $modulo)
                    @php
                        $estilo = $modulo['estilo'] ?? \App\Support\MenuSiger::estilo('siger');
                        $svgPath = $modulo['svg_path'] ?? 'M4 6h16M4 12h16M4 18h16';
                    @endphp

                    @if ($modulo['disponible'])
                        <a href="{{ $modulo['url'] }}"
                           class="group block bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-md {{ $estilo['card_hover'] }} transition-all duration-200 hover:-translate-y-0.5">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl {{ $estilo['icon_box'] }} flex items-center justify-center shrink-0 transition-colors">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $svgPath }}" />
                                    </svg>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h3 class="font-bold text-slate-800 {{ $estilo['title_hover'] }} transition-colors">
                                        {{ __($modulo['etiqueta']) }}
                                    </h3>
                                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                        {{ $modulo['descripcion'] ?? '' }}
                                    </p>
                                </div>
                            </div>
                        </a>
                    @else
                        <div class="group bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-md {{ $estilo['card_hover'] }} transition-all duration-200 hover:-translate-y-0.5">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl {{ $estilo['icon_box'] }} flex items-center justify-center shrink-0 transition-colors">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $svgPath }}" />
                                    </svg>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <h3 class="font-bold text-slate-800 {{ $estilo['title_hover'] }} transition-colors">
                                            {{ __($modulo['etiqueta']) }}
                                        </h3>
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200 shrink-0">
                                            Fase {{ $modulo['fase'] ?? '-' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                        {{ $modulo['descripcion'] ?? '' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</div>
