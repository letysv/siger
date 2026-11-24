<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Departamento;
use App\Models\Estado;
use App\Models\Partida;
use App\Models\Servicio;
use App\Models\ServicioAcuse;
use App\Models\ServicioArchivo;
use App\Models\TipoServicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Lógica de negocio y consultas acotadas para expedientes y sus documentos (RF-007, RF-008, RF-009, RF-012, RF-013).
 */
class ExpedienteService
{
    private const DISCO_DOCUMENTOS = 'local';

    public function __construct(
        private readonly PartidaService $partidaService,
    ) {}

    /**
     * Consulta de expedientes filtrada estrictamente según el alcance de la Policy.
     *
     * @return Builder<Servicio>
     */
    public function queryVisiblesPara(User $usuario): Builder
    {
        if (! $usuario->activo || ! $usuario->checkPermissionTo('servicios.ver', 'web')) {
            return Servicio::query()->whereRaw('1 = 0');
        }

        if ($usuario->hasAnyRole(['Super usuario', 'Administrador'])) {
            return Servicio::query();
        }

        if ($usuario->departamento_id === null && $usuario->hasRole('Recepción/Coordinador')) {
            return Servicio::query()->where(function (Builder $query) use ($usuario): void {
                $query->where('usuario_id', $usuario->id)
                    ->orWhereHas('partidas', fn (Builder $partidas) => $partidas->whereNull('departamento_id'));
            });
        }

        return Servicio::query()->whereHas('partidas', function (Builder $query) use ($usuario): void {
            $query->whereRaw('1 = 0');

            if ($usuario->departamento_id !== null
                && $usuario->hasAnyRole(['Jefe', 'Recepción/Coordinador'])) {
                $query->orWhere('departamento_id', $usuario->departamento_id);
            }

            if ($usuario->hasRole('Operativo')) {
                $query->orWhereHas('asignaciones', function (Builder $asignaciones) use ($usuario): void {
                    $asignaciones->where('activo', true)
                        ->whereNull('fecha_finalizacion')
                        ->whereHas('operativo', fn (Builder $op) => $op->where('user_id', $usuario->id));
                });
            }
        });
    }

    /**
     * Busca posibles duplicados por área solicitante, ejercicio y número de oficio (RF-012).
     *
     * @return Collection<int, Servicio>
     */
    public function buscarPosiblesDuplicados(?int $areaSolicitanteId, ?int $ejercicio, ?string $numeroOficio): Collection
    {
        $oficio = trim((string) ($numeroOficio ?? ''));
        if ($areaSolicitanteId === null || $ejercicio === null || $oficio === '') {
            return new Collection;
        }

        return Servicio::query()
            ->with(['areaSolicitante', 'estado'])
            ->where('area_solicitante_id', $areaSolicitanteId)
            ->where('ejercicio', $ejercicio)
            ->where('numero_oficio', $oficio)
            ->latest('fecha_recepcion')
            ->limit(5)
            ->get();
    }

    /**
     * Registra un expediente (servicios), su partida inicial (numero_partida = 1) y adjuntos PDF opcionales.
     * No genera folio departamental durante la recepción (RF-007 / RF-010).
     *
     * @param  array<int, UploadedFile>  $archivos
     */
    public function registrar(array $datos, User $usuario, array $archivos = [], ?UploadedFile $acuse = null): Servicio
    {
        Gate::forUser($usuario)->authorize('create', Servicio::class);

        $normalizados = $this->normalizarDatosExpediente($datos);
        $validados = $this->validarDatosExpediente($normalizados, $usuario);
        $this->validarArchivosAdjuntos($archivos, $acuse);

        $rutasGuardadas = [];

        try {
            return DB::transaction(function () use ($validados, $usuario, $archivos, $acuse, &$rutasGuardadas): Servicio {
                $estadoRegistrado = Estado::where('clave', 'REGISTRADO')->firstOrFail();
                $fechaRecepcion = Carbon::parse($validados['fecha_recepcion']);
                $ejercicio = (int) $fechaRecepcion->year;

                $servicio = Servicio::create([
                    'numero_oficio' => $validados['numero_oficio'],
                    'fecha_oficio' => $validados['fecha_oficio'],
                    'fecha_recepcion' => $fechaRecepcion,
                    'ejercicio' => $ejercicio,
                    'area_solicitante_id' => $validados['area_solicitante_id'],
                    'nombre_solicitante' => $validados['nombre_solicitante'],
                    'dirigido_a' => $validados['dirigido_a'],
                    'asunto' => $validados['asunto'],
                    'descripcion' => $validados['descripcion'],
                    'es_via_rapida' => $validados['es_via_rapida'],
                    'estado_id' => $estadoRegistrado->id,
                    'usuario_id' => $usuario->id,
                ]);

                Partida::create([
                    'servicio_id' => $servicio->id,
                    'partida_origen_id' => null,
                    'numero_partida' => 1,
                    'servicio_folio_id' => null,
                    'tipo_servicio_id' => $validados['tipo_servicio_id'],
                    'concepto' => $validados['concepto'],
                    'cantidad_solicitada' => $validados['cantidad_solicitada'],
                    'cantidad_atendida' => null,
                    'unidad_medida' => $validados['unidad_medida'],
                    'departamento_id' => $validados['departamento_id'],
                    'observaciones' => $validados['observaciones'],
                    'estado_id' => $estadoRegistrado->id,
                ]);

                foreach ($archivos as $archivo) {
                    $registro = $this->crearArchivoConNomenclatura($servicio, $archivo, $rutasGuardadas);
                    unset($registro);
                }

                if ($acuse !== null) {
                    $this->crearAcuseConNomenclatura($servicio, $acuse, null, null, null, $rutasGuardadas);
                }

                return $servicio->load(['areaSolicitante', 'estado', 'partidas.departamento', 'partidas.tipoServicio', 'archivos', 'acuses']);
            });
        } catch (Throwable $exception) {
            foreach ($rutasGuardadas as $ruta) {
                Storage::disk(self::DISCO_DOCUMENTOS)->delete($ruta);
            }

            throw $exception;
        }
    }

    /**
     * Agrega una partida adicional a un expediente existente con bloqueo pesimista y recálculo RF-015.
     */
    public function agregarPartida(Servicio $servicio, array $datos, User $usuario): Partida
    {
        Gate::forUser($usuario)->authorize('create', [Partida::class, $servicio]);

        $validados = $this->validarDatosPartida($datos);

        return DB::transaction(function () use ($servicio, $validados): Partida {
            $expedienteBloqueado = Servicio::whereKey($servicio->getKey())->lockForUpdate()->firstOrFail();
            $estadoRegistrado = Estado::where('clave', 'REGISTRADO')->firstOrFail();
            $siguienteNumero = ((int) $expedienteBloqueado->partidas()->max('numero_partida')) + 1;

            $partida = Partida::create([
                'servicio_id' => $expedienteBloqueado->id,
                'partida_origen_id' => null,
                'numero_partida' => $siguienteNumero,
                'servicio_folio_id' => null,
                'tipo_servicio_id' => $validados['tipo_servicio_id'],
                'concepto' => $validados['concepto'],
                'cantidad_solicitada' => $validados['cantidad_solicitada'],
                'unidad_medida' => $validados['unidad_medida'],
                'departamento_id' => $validados['departamento_id'],
                'observaciones' => $validados['observaciones'],
                'estado_id' => $estadoRegistrado->id,
            ]);

            $this->partidaService->recalcularEstadoExpediente($expedienteBloqueado);

            return $partida;
        });
    }

    /**
     * Permite completar o corregir una partida que todavía no ha sido turnada (RF-013 / RF-014).
     */
    public function actualizarPartidaPreparacion(Partida $partida, array $datos, User $usuario): Partida
    {
        Gate::forUser($usuario)->authorize('update', $partida);

        $validados = $this->validarDatosPartida($datos);

        if (! $usuario->hasAnyRole(['Super usuario', 'Administrador'])
            && $usuario->hasRole('Recepción/Coordinador')
            && $usuario->departamento_id !== null
            && $validados['departamento_id'] !== null
            && (int) $validados['departamento_id'] !== (int) $usuario->departamento_id) {
            throw ValidationException::withMessages([
                'departamento_id' => 'Solo puedes proponer tu departamento de adscripción.',
            ]);
        }

        return DB::transaction(function () use ($partida, $validados, $usuario): Partida {
            $partidaBloqueada = Partida::whereKey($partida->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($usuario)->authorize('update', $partidaBloqueada);

            $partidaBloqueada->update([
                'tipo_servicio_id' => $validados['tipo_servicio_id'],
                'concepto' => $validados['concepto'],
                'cantidad_solicitada' => $validados['cantidad_solicitada'],
                'unidad_medida' => $validados['unidad_medida'],
                'departamento_id' => $validados['departamento_id'],
                'observaciones' => $validados['observaciones'],
            ]);

            return $partidaBloqueada->fresh(['tipoServicio', 'departamento', 'estado']);
        });
    }

    public function adjuntarArchivo(Servicio $servicio, UploadedFile $archivo, User $usuario): ServicioArchivo
    {
        Gate::forUser($usuario)->authorize('adjuntarDocumento', $servicio);
        $this->validarArchivosAdjuntos([$archivo], null);

        $rutasGuardadas = [];

        try {
            return DB::transaction(fn (): ServicioArchivo => $this->crearArchivoConNomenclatura($servicio, $archivo, $rutasGuardadas));
        } catch (Throwable $exception) {
            foreach ($rutasGuardadas as $ruta) {
                Storage::disk(self::DISCO_DOCUMENTOS)->delete($ruta);
            }

            throw $exception;
        }
    }

    public function registrarAcuse(
        Servicio $servicio,
        UploadedFile $archivo,
        User $usuario,
        ?ServicioAcuse $acuseOriginal = null,
        ?string $motivoSustitucion = null,
    ): ServicioAcuse {
        Gate::forUser($usuario)->authorize('adjuntarDocumento', $servicio);
        $this->validarArchivosAdjuntos([], $archivo);

        $motivo = $this->textoOpcional($motivoSustitucion);

        if ($acuseOriginal !== null) {
            if ((int) $acuseOriginal->servicio_id !== (int) $servicio->id) {
                throw ValidationException::withMessages([
                    'acuse_original_id' => 'El acuse seleccionado no pertenece a este expediente.',
                ]);
            }

            if ($motivo === null) {
                throw ValidationException::withMessages([
                    'motivo_sustitucion' => 'Debes indicar el motivo de sustitución del acuse.',
                ]);
            }
        }

        $rutasGuardadas = [];

        try {
            return DB::transaction(function () use ($servicio, $archivo, $usuario, $acuseOriginal, $motivo, &$rutasGuardadas): ServicioAcuse {
                if ($acuseOriginal !== null) {
                    $originalBloqueado = ServicioAcuse::whereKey($acuseOriginal->getKey())->lockForUpdate()->firstOrFail();

                    if ($originalBloqueado->sustituto()->exists()) {
                        throw ValidationException::withMessages([
                            'acuse_original_id' => 'Este acuse ya fue sustituido previamente.',
                        ]);
                    }
                }

                return $this->crearAcuseConNomenclatura(
                    $servicio,
                    $archivo,
                    $acuseOriginal?->id,
                    $acuseOriginal !== null ? $motivo : null,
                    $acuseOriginal !== null ? $usuario->id : null,
                    $rutasGuardadas,
                );
            });
        } catch (Throwable $exception) {
            foreach ($rutasGuardadas as $ruta) {
                Storage::disk(self::DISCO_DOCUMENTOS)->delete($ruta);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, string>  $rutasGuardadas
     */
    private function crearArchivoConNomenclatura(Servicio $servicio, UploadedFile $archivo, array &$rutasGuardadas): ServicioArchivo
    {
        $temporal = 'tmp-'.Str::uuid().'.pdf';
        $registro = ServicioArchivo::create([
            'servicio_id' => $servicio->id,
            'nombre' => $temporal,
            'ruta' => $temporal,
        ]);

        // RF-008: SERVICIO_[servicio_id]_[identificador_archivo].pdf en servicios/[servicio_id]/archivos/
        $nombreFinal = "SERVICIO_{$servicio->id}_{$registro->id}.pdf";
        $rutaFinal = $archivo->storeAs("servicios/{$servicio->id}/archivos", $nombreFinal, self::DISCO_DOCUMENTOS);
        $rutasGuardadas[] = $rutaFinal;

        $registro->update([
            'nombre' => $nombreFinal,
            'ruta' => $rutaFinal,
        ]);

        return $registro;
    }

    /**
     * @param  array<int, string>  $rutasGuardadas
     */
    private function crearAcuseConNomenclatura(
        Servicio $servicio,
        UploadedFile $archivo,
        ?int $acuseOriginalId,
        ?string $motivoSustitucion,
        ?int $sustituidoPor,
        array &$rutasGuardadas,
    ): ServicioAcuse {
        $temporal = 'tmp-'.Str::uuid().'.pdf';
        $registro = ServicioAcuse::create([
            'servicio_id' => $servicio->id,
            'nombre' => $temporal,
            'ruta' => $temporal,
            'acuse_original_id' => $acuseOriginalId,
            'motivo_sustitucion' => $motivoSustitucion,
            'sustituido_por' => $sustituidoPor,
        ]);

        // RF-009: ACUSE_[servicio_id]_[identificador_acuse].pdf en servicios/[servicio_id]/acuses/
        $nombreFinal = "ACUSE_{$servicio->id}_{$registro->id}.pdf";
        $rutaFinal = $archivo->storeAs("servicios/{$servicio->id}/acuses", $nombreFinal, self::DISCO_DOCUMENTOS);
        $rutasGuardadas[] = $rutaFinal;

        $registro->update([
            'nombre' => $nombreFinal,
            'ruta' => $rutaFinal,
        ]);

        return $registro;
    }

    /**
     * @return array<string, mixed>
     */
    private function validarDatosPartida(array $datos): array
    {
        $normalizados = [
            'tipo_servicio_id' => $this->enteroOpcional($datos['tipo_servicio_id'] ?? null),
            'concepto' => trim((string) ($datos['concepto'] ?? '')),
            'cantidad_solicitada' => ($datos['cantidad_solicitada'] ?? '') !== '' ? $datos['cantidad_solicitada'] : null,
            'unidad_medida' => $this->textoOpcional($datos['unidad_medida'] ?? null),
            'departamento_id' => $this->enteroOpcional($datos['departamento_id'] ?? null),
            'observaciones' => $this->textoOpcional($datos['observaciones'] ?? null),
        ];

        $validados = Validator::make($normalizados, [
            'tipo_servicio_id' => ['nullable', 'integer', 'exists:cat_tipo_servicios,id'],
            'concepto' => ['required', 'string', 'max:2000'],
            'cantidad_solicitada' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'unidad_medida' => ['nullable', 'string', 'max:100'],
            'departamento_id' => ['nullable', 'integer', 'exists:cat_departamentos,id'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $this->validarTipoServicioYCantidad(
            $validados['tipo_servicio_id'],
            $validados['cantidad_solicitada'],
            $validados['unidad_medida'],
        );
        $this->validarDepartamentoActivo($validados['departamento_id']);

        return $validados;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizarDatosExpediente(array $datos): array
    {
        return [
            'numero_oficio' => $this->textoOpcional($datos['numero_oficio'] ?? null),
            'fecha_oficio' => $this->textoOpcional($datos['fecha_oficio'] ?? null),
            'fecha_recepcion' => trim((string) ($datos['fecha_recepcion'] ?? '')),
            'area_solicitante_id' => $this->enteroOpcional($datos['area_solicitante_id'] ?? null),
            'nombre_solicitante' => trim((string) ($datos['nombre_solicitante'] ?? '')),
            'dirigido_a' => trim((string) ($datos['dirigido_a'] ?? '')),
            'asunto' => trim((string) ($datos['asunto'] ?? '')),
            'descripcion' => $this->textoOpcional($datos['descripcion'] ?? null),
            'es_via_rapida' => (bool) ($datos['es_via_rapida'] ?? false),
            'tipo_servicio_id' => $this->enteroOpcional($datos['tipo_servicio_id'] ?? null),
            'concepto' => $this->textoOpcional($datos['concepto'] ?? null),
            'cantidad_solicitada' => ($datos['cantidad_solicitada'] ?? '') !== '' ? $datos['cantidad_solicitada'] : null,
            'unidad_medida' => $this->textoOpcional($datos['unidad_medida'] ?? null),
            'departamento_id' => $this->enteroOpcional($datos['departamento_id'] ?? null),
            'observaciones' => $this->textoOpcional($datos['observaciones'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validarDatosExpediente(array $normalizados, User $usuario): array
    {
        $validados = Validator::make($normalizados, [
            'numero_oficio' => ['nullable', 'string', 'max:100'],
            'fecha_oficio' => ['nullable', 'date'],
            'fecha_recepcion' => ['required', 'date'],
            'area_solicitante_id' => ['required', 'integer', 'exists:cat_areas,id'],
            'nombre_solicitante' => ['required', 'string', 'max:255'],
            'dirigido_a' => ['required', 'string', 'max:255'],
            'asunto' => ['required', 'string', 'max:500'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'es_via_rapida' => ['required', 'boolean'],
            'tipo_servicio_id' => ['nullable', 'integer', 'exists:cat_tipo_servicios,id'],
            'concepto' => ['nullable', 'string', 'max:2000'],
            'cantidad_solicitada' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'unidad_medida' => ['nullable', 'string', 'max:100'],
            'departamento_id' => ['nullable', 'integer', 'exists:cat_departamentos,id'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $area = Area::findOrFail($validados['area_solicitante_id']);
        if (! $area->activo) {
            throw ValidationException::withMessages([
                'area_solicitante_id' => 'El área solicitante seleccionada está inactiva.',
            ]);
        }

        $this->validarTipoServicioYCantidad(
            $validados['tipo_servicio_id'],
            $validados['cantidad_solicitada'],
            $validados['unidad_medida'],
        );
        $this->validarDepartamentoActivo($validados['departamento_id']);

        if ($validados['es_via_rapida']) {
            if ($validados['departamento_id'] === null) {
                throw ValidationException::withMessages([
                    'departamento_id' => 'Para un expediente de vía rápida debes seleccionar el departamento propuesto.',
                ]);
            }

            if (! $usuario->hasAnyRole(['Super usuario', 'Administrador'])
                && $usuario->hasRole('Recepción/Coordinador')
                && (int) $usuario->departamento_id !== (int) $validados['departamento_id']) {
                throw ValidationException::withMessages([
                    'departamento_id' => 'En vía rápida solo puedes proponer tu departamento de adscripción.',
                ]);
            }
        }

        if ($validados['concepto'] === null) {
            $validados['concepto'] = $validados['asunto'];
        }

        return $validados;
    }

    private function validarTipoServicioYCantidad(?int $tipoServicioId, mixed $cantidadSolicitada, ?string $unidadMedida): void
    {
        if ($tipoServicioId === null) {
            return;
        }

        $tipo = TipoServicio::findOrFail($tipoServicioId);
        if (! $tipo->activo) {
            throw ValidationException::withMessages([
                'tipo_servicio_id' => 'El tipo de servicio seleccionado está inactivo.',
            ]);
        }

        if ($tipo->requiere_cantidad) {
            if ($cantidadSolicitada === null || (float) $cantidadSolicitada <= 0) {
                throw ValidationException::withMessages([
                    'cantidad_solicitada' => 'Este tipo de servicio requiere capturar una cantidad solicitada mayor a cero.',
                ]);
            }

            if ($unidadMedida === null || trim($unidadMedida) === '') {
                throw ValidationException::withMessages([
                    'unidad_medida' => 'Este tipo de servicio requiere capturar la unidad de medida.',
                ]);
            }
        }
    }

    private function validarDepartamentoActivo(?int $departamentoId): void
    {
        if ($departamentoId === null) {
            return;
        }

        $departamento = Departamento::findOrFail($departamentoId);
        if (! $departamento->activo) {
            throw ValidationException::withMessages([
                'departamento_id' => 'El departamento seleccionado está inactivo.',
            ]);
        }
    }

    /**
     * RF-008 y RF-009: los archivos del oficio y acuses de recepción deben ser únicamente PDF de hasta 10 MB.
     *
     * @param  array<int, UploadedFile>  $archivos
     */
    private function validarArchivosAdjuntos(array $archivos, ?UploadedFile $acuse): void
    {
        Validator::make(
            ['archivos' => $archivos, 'acuse' => $acuse],
            [
                'archivos' => ['array', 'max:10'],
                'archivos.*' => ['file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
                'acuse' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
            ],
            [
                'archivos.*.mimes' => 'Los archivos del oficio deben ser únicamente en formato PDF.',
                'archivos.*.mimetypes' => 'Los archivos del oficio deben ser únicamente en formato PDF válido.',
                'archivos.*.max' => 'Cada archivo adjunto no debe superar los 10 MB.',
                'acuse.mimes' => 'El acuse de recepción debe ser únicamente en formato PDF.',
                'acuse.mimetypes' => 'El acuse de recepción debe ser únicamente en formato PDF válido.',
                'acuse.max' => 'El acuse no debe superar los 10 MB.',
            ]
        )->validate();
    }

    private function textoOpcional(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }

    private function enteroOpcional(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) $valor;
    }
}
