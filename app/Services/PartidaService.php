<?php

namespace App\Services;

use App\Models\ConsecutivoFolio;
use App\Models\Departamento;
use App\Models\Estado;
use App\Models\HistorialDevolucion;
use App\Models\HistorialTurnado;
use App\Models\Operativo;
use App\Models\OperativoPartida;
use App\Models\Partida;
use App\Models\RegistroOperativo;
use App\Models\Servicio;
use App\Models\ServicioFolio;
use App\Models\TipoServicio;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Lógica de negocio y controles transaccionales de partidas, turnados, asignaciones, devoluciones y conclusión (RF-010 a RF-019, RF-026).
 */
class PartidaService
{
    private const DISCO_DOCUMENTOS = 'local';
    /**
     * Consulta de partidas acotada al alcance autorizado del usuario.
     *
     * @return Builder<Partida>
     */
    public function queryVisiblesPara(User $usuario): Builder
    {
        if (! $usuario->activo || ! $usuario->checkPermissionTo('partidas.ver', 'web')) {
            return Partida::query()->whereRaw('1 = 0');
        }

        if ($usuario->hasAnyRole(['Super usuario', 'Administrador'])) {
            return Partida::query();
        }

        return Partida::query()->where(function (Builder $query) use ($usuario): void {
            $query->whereRaw('1 = 0');

            if ($usuario->departamento_id === null && $usuario->hasRole('Recepción/Coordinador')) {
                $query->orWhereNull('departamento_id')
                    ->orWhereHas('servicio', fn (Builder $s) => $s->where('usuario_id', $usuario->id));
            }

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
     * Primer turnado formal de una partida hacia un departamento (RF-010 / RF-013 / RF-014).
     * Genera el folio SIGLA/MES/CONSECUTIVO/AA o reutiliza el ya asignado al expediente en ese departamento.
     */
    public function turnar(Partida $partida, Departamento $destino, User $usuario, ?string $motivo = null): Partida
    {
        Gate::forUser($usuario)->authorize('turnar', [$partida, $destino]);

        $validados = Validator::make(
            ['motivo' => $this->textoOpcional($motivo)],
            ['motivo' => ['nullable', 'string', 'max:1000']],
        )->validate();

        return DB::transaction(function () use ($partida, $destino, $usuario, $validados): Partida {
            $servicio = Servicio::whereKey($partida->servicio_id)->lockForUpdate()->firstOrFail();
            $partidaBloqueada = Partida::with('tipoServicio')->whereKey($partida->getKey())->lockForUpdate()->firstOrFail();
            $destinoActual = Departamento::whereKey($destino->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($usuario)->allows('turnar', [$partidaBloqueada, $destinoActual])) {
                throw new AuthorizationException('La partida o el departamento destino ya no cumplen las condiciones para el turnado.');
            }

            $this->validarInformacionObligatoriaAntesDeTurnar($partidaBloqueada);

            $ahora = now();
            $folioDestino = $this->obtenerOGenerarFolio($servicio, $destinoActual, $ahora);
            $estadoTurnado = Estado::where('clave', 'TURNADO')->firstOrFail();

            // RF-014: en el primer turno los campos de origen permanecen en NULL.
            HistorialTurnado::create([
                'partida_id' => $partidaBloqueada->id,
                'departamento_origen_id' => null,
                'departamento_destino_id' => $destinoActual->id,
                'servicio_folio_origen_id' => null,
                'servicio_folio_destino_id' => $folioDestino->id,
                'usuario_id' => $usuario->id,
                'motivo' => $validados['motivo'],
                'fecha' => $ahora,
            ]);

            $partidaBloqueada->update([
                'departamento_id' => $destinoActual->id,
                'servicio_folio_id' => $folioDestino->id,
                'estado_id' => $estadoTurnado->id,
            ]);

            $this->recalcularEstadoExpediente($servicio);

            return $partidaBloqueada->fresh(['servicio', 'departamento', 'estado', 'folio']);
        });
    }

    /**
     * Re-turnado de una partida ya turnada, en atención o regresada a revisión hacia otro departamento distinto.
     * Finaliza administrativamente las asignaciones operativas activas del departamento de origen (RF-014 / RF-016).
     */
    public function returnar(Partida $partida, Departamento $destino, User $usuario, string $motivo): Partida
    {
        Gate::forUser($usuario)->authorize('returnar', [$partida, $destino]);

        $validados = Validator::make(
            ['motivo' => trim($motivo)],
            ['motivo' => ['required', 'string', 'max:1000']],
            ['motivo.required' => 'Debes indicar el motivo del re-turnado.'],
        )->validate();

        return DB::transaction(function () use ($partida, $destino, $usuario, $validados): Partida {
            $servicio = Servicio::whereKey($partida->servicio_id)->lockForUpdate()->firstOrFail();
            $partidaBloqueada = Partida::whereKey($partida->getKey())->lockForUpdate()->firstOrFail();
            $destinoActual = Departamento::whereKey($destino->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($usuario)->allows('returnar', [$partidaBloqueada, $destinoActual])) {
                throw new AuthorizationException('La partida o el departamento destino ya no cumplen las condiciones para el re-turnado.');
            }

            $ahora = now();
            $this->finalizarAsignacionesActivas($partidaBloqueada, $ahora);
            $folioDestino = $this->obtenerOGenerarFolio($servicio, $destinoActual, $ahora);
            $estadoTurnado = Estado::where('clave', 'TURNADO')->firstOrFail();

            HistorialTurnado::create([
                'partida_id' => $partidaBloqueada->id,
                'departamento_origen_id' => $partidaBloqueada->departamento_id,
                'departamento_destino_id' => $destinoActual->id,
                'servicio_folio_origen_id' => $partidaBloqueada->servicio_folio_id,
                'servicio_folio_destino_id' => $folioDestino->id,
                'usuario_id' => $usuario->id,
                'motivo' => $validados['motivo'],
                'fecha' => $ahora,
            ]);

            $partidaBloqueada->update([
                'departamento_id' => $destinoActual->id,
                'servicio_folio_id' => $folioDestino->id,
                'estado_id' => $estadoTurnado->id,
            ]);

            $this->recalcularEstadoExpediente($servicio);

            return $partidaBloqueada->fresh(['servicio', 'departamento', 'estado', 'folio']);
        });
    }

    /**
     * Regresa a Dirección (EN_REVISION) una partida mal turnada para que Administración la re-turne al departamento correcto.
     */
    public function regresarADireccion(Partida $partida, User $usuario, string $motivo): Partida
    {
        Gate::forUser($usuario)->authorize('regresarDireccion', $partida);

        $validados = Validator::make(
            ['motivo' => trim($motivo)],
            ['motivo' => ['required', 'string', 'max:1000']],
            ['motivo.required' => 'Debes indicar el motivo para regresar la partida a Dirección.'],
        )->validate();

        return DB::transaction(function () use ($partida, $usuario, $validados): Partida {
            $servicio = Servicio::whereKey($partida->servicio_id)->lockForUpdate()->firstOrFail();
            $partidaBloqueada = Partida::with('departamento')->whereKey($partida->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($usuario)->allows('regresarDireccion', $partidaBloqueada)) {
                throw new AuthorizationException('La partida ya no puede regresarse a Dirección en su estado actual.');
            }

            $ahora = now();
            $this->finalizarAsignacionesActivas($partidaBloqueada, $ahora);
            $estadoRevision = Estado::where('clave', 'EN_REVISION')->firstOrFail();

            $nota = sprintf(
                '[Regresada a Dirección por %s (%s) el %s]: %s',
                $usuario->name,
                $partidaBloqueada->departamento?->siglas ?? 'Depto',
                $ahora->format('d/m/Y H:i'),
                $validados['motivo']
            );

            $observacionesActualizadas = trim(implode("\n\n", array_filter([
                $partidaBloqueada->observaciones,
                $nota,
            ])));

            $partidaBloqueada->update([
                'estado_id' => $estadoRevision->id,
                'observaciones' => $observacionesActualizadas,
            ]);

            activity('partidas')
                ->causedBy($usuario)
                ->performedOn($partidaBloqueada)
                ->withProperties([
                    'departamento_origen_id' => $partidaBloqueada->departamento_id,
                    'servicio_folio_id' => $partidaBloqueada->servicio_folio_id,
                    'motivo' => $validados['motivo'],
                ])
                ->log('Partida regresada a Dirección para re-turnado');

            $this->recalcularEstadoExpediente($servicio);

            return $partidaBloqueada->fresh(['servicio', 'departamento', 'estado', 'folio']);
        });
    }

    /**
     * Cancelación definitiva de una partida no terminal con registro obligatorio de motivo (RF-026).
     * Finaliza asignaciones operativas activas y cierra devoluciones abiertas si aplica.
     */
    public function cancelar(Partida $partida, User $usuario, string $motivo): Partida
    {
        Gate::forUser($usuario)->authorize('cancelar', $partida);

        $validados = Validator::make(
            ['motivo' => trim($motivo)],
            ['motivo' => ['required', 'string', 'max:1000']],
            ['motivo.required' => 'Debes indicar el motivo de cancelación.'],
        )->validate();

        return DB::transaction(function () use ($partida, $usuario, $validados): Partida {
            $servicio = Servicio::whereKey($partida->servicio_id)->lockForUpdate()->firstOrFail();
            $partidaBloqueada = Partida::with('estado')->whereKey($partida->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($usuario)->allows('cancelar', $partidaBloqueada)) {
                throw new AuthorizationException('La partida ya no puede cancelarse en su estado actual.');
            }

            $ahora = now();
            $this->finalizarAsignacionesActivas($partidaBloqueada, $ahora);

            if ($partidaBloqueada->estado?->clave === 'DEVUELTO_AREA') {
                $devolucionAbierta = $partidaBloqueada->devoluciones()
                    ->whereNull('resultado_cierre')
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                if ($devolucionAbierta !== null) {
                    $devolucionAbierta->update([
                        'resultado_cierre' => 'CANCELADA',
                        'cerrado_por' => $usuario->id,
                        'fecha_cierre' => $ahora,
                    ]);
                }
            }

            $estadoCancelado = Estado::where('clave', 'CANCELADO')->firstOrFail();

            $partidaBloqueada->update([
                'estado_id' => $estadoCancelado->id,
                'motivo_cancelacion' => $validados['motivo'],
                'cancelado_por' => $usuario->id,
                'fecha_cancelacion' => $ahora,
            ]);

            $this->recalcularEstadoExpediente($servicio);

            return $partidaBloqueada->fresh(['servicio', 'departamento', 'estado', 'folio', 'usuarioCancelacion']);
        });
    }

    /**
     * Asigna un operativo activo del mismo departamento a una partida en TURNADO o EN_ATENCION (RF-016).
     */
    public function asignarOperativo(Partida $partida, Operativo $operativo, User $usuario): OperativoPartida
    {
        Gate::forUser($usuario)->authorize('create', [OperativoPartida::class, $partida, $operativo]);

        return DB::transaction(function () use ($partida, $operativo, $usuario): OperativoPartida {
            $partidaBloqueada = Partida::whereKey($partida->getKey())->lockForUpdate()->firstOrFail();
            $operativoActual = Operativo::whereKey($operativo->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($usuario)->allows('create', [OperativoPartida::class, $partidaBloqueada, $operativoActual])) {
                throw new AuthorizationException('No es posible asignar al operativo seleccionado en esta partida.');
            }

            return OperativoPartida::create([
                'partida_id' => $partidaBloqueada->id,
                'operativo_id' => $operativoActual->id,
                'fecha_asignacion' => now(),
                'fecha_finalizacion' => null,
                'activo' => true,
            ]);
        });
    }

    /**
     * Finaliza una asignación operativa activa conservando el historial (RF-016).
     */
    public function finalizarAsignacion(OperativoPartida $asignacion, User $usuario): OperativoPartida
    {
        Gate::forUser($usuario)->authorize('finalizar', $asignacion);

        return DB::transaction(function () use ($asignacion, $usuario): OperativoPartida {
            Partida::whereKey($asignacion->partida_id)->lockForUpdate()->firstOrFail();
            $asignacionBloqueada = OperativoPartida::with('partida')->whereKey($asignacion->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($usuario)->allows('finalizar', $asignacionBloqueada)) {
                throw new AuthorizationException('La asignación ya no puede finalizarse.');
            }

            $ahora = now();
            $fechaFinal = $asignacionBloqueada->fecha_asignacion !== null && $ahora->lt($asignacionBloqueada->fecha_asignacion)
                ? $asignacionBloqueada->fecha_asignacion
                : $ahora;

            $asignacionBloqueada->update([
                'activo' => false,
                'fecha_finalizacion' => $fechaFinal,
            ]);

            return $asignacionBloqueada->fresh(['operativo', 'partida']);
        });
    }

    /**
     * Sustituye de forma atómica a un operativo asignado por otro del mismo departamento (RF-016).
     */
    public function sustituirOperativo(OperativoPartida $asignacionAnterior, Operativo $nuevoOperativo, User $usuario): OperativoPartida
    {
        if ((int) $asignacionAnterior->operativo_id === (int) $nuevoOperativo->getKey()) {
            throw ValidationException::withMessages([
                'operativo_sustituto_id' => 'Debes seleccionar un operativo distinto al actualmente asignado.',
            ]);
        }

        $partida = $asignacionAnterior->partida ?? Partida::findOrFail($asignacionAnterior->partida_id);
        Gate::forUser($usuario)->authorize('finalizar', $asignacionAnterior);
        Gate::forUser($usuario)->authorize('create', [OperativoPartida::class, $partida, $nuevoOperativo]);

        return DB::transaction(function () use ($asignacionAnterior, $nuevoOperativo, $usuario): OperativoPartida {
            $partidaBloqueada = Partida::whereKey($asignacionAnterior->partida_id)->lockForUpdate()->firstOrFail();
            $anteriorBloqueada = OperativoPartida::with('partida')->whereKey($asignacionAnterior->getKey())->lockForUpdate()->firstOrFail();
            $nuevoActual = Operativo::whereKey($nuevoOperativo->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($usuario)->allows('finalizar', $anteriorBloqueada)
                || ! Gate::forUser($usuario)->allows('create', [OperativoPartida::class, $partidaBloqueada, $nuevoActual])) {
                throw new AuthorizationException('No se cumplen las condiciones para sustituir al operativo.');
            }

            $ahora = now();
            $fechaFinal = $anteriorBloqueada->fecha_asignacion !== null && $ahora->lt($anteriorBloqueada->fecha_asignacion)
                ? $anteriorBloqueada->fecha_asignacion
                : $ahora;

            $anteriorBloqueada->update([
                'activo' => false,
                'fecha_finalizacion' => $fechaFinal,
            ]);

            $nueva = OperativoPartida::create([
                'partida_id' => $partidaBloqueada->id,
                'operativo_id' => $nuevoActual->id,
                'fecha_asignacion' => $ahora,
                'fecha_finalizacion' => null,
                'activo' => true,
            ]);

            activity('asignaciones')
                ->causedBy($usuario)
                ->performedOn($nueva)
                ->withProperties([
                    'asignacion_anterior_id' => $anteriorBloqueada->id,
                    'operativo_anterior_id' => $anteriorBloqueada->operativo_id,
                    'operativo_nuevo_id' => $nuevoActual->id,
                ])
                ->log('Operativo sustituido en partida');

            return $nueva->load(['operativo', 'partida']);
        });
    }

    /**
     * Inicia formalmente la atención de una partida en estado TURNADO (RF-016).
     */
    public function iniciarAtencion(Partida $partida, User $usuario): Partida
    {
        Gate::forUser($usuario)->authorize('iniciarAtencion', $partida);

        return DB::transaction(function () use ($partida, $usuario): Partida {
            $servicio = Servicio::whereKey($partida->servicio_id)->lockForUpdate()->firstOrFail();
            $partidaBloqueada = Partida::whereKey($partida->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($usuario)->allows('iniciarAtencion', $partidaBloqueada)) {
                throw new AuthorizationException('La partida ya no puede pasar a En atención.');
            }

            $estadoEnAtencion = Estado::where('clave', 'EN_ATENCION')->firstOrFail();

            $partidaBloqueada->update([
                'estado_id' => $estadoEnAtencion->id,
                'fecha_inicio' => $partidaBloqueada->fecha_inicio ?? now(),
            ]);

            $this->recalcularEstadoExpediente($servicio);

            return $partidaBloqueada->fresh(['servicio', 'departamento', 'estado', 'folio']);
        });
    }

    /**
     * Devuelve una partida al área solicitante con observaciones obligatorias (RF-018).
     * Conserva asignaciones operativas activas en suspensión operativa (sin finalizarlas).
     */
    public function devolverAlArea(Partida $partida, User $usuario, string $observaciones): HistorialDevolucion
    {
        Gate::forUser($usuario)->authorize('devolver', $partida);

        $validados = Validator::make(
            ['observaciones' => trim($observaciones)],
            ['observaciones' => ['required', 'string', 'max:2000']],
            ['observaciones.required' => 'Debes capturar las observaciones de la devolución.'],
        )->validate();

        return DB::transaction(function () use ($partida, $usuario, $validados): HistorialDevolucion {
            $servicio = Servicio::whereKey($partida->servicio_id)->lockForUpdate()->firstOrFail();
            $partidaBloqueada = Partida::whereKey($partida->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($usuario)->allows('devolver', $partidaBloqueada)) {
                throw new AuthorizationException('La partida no puede devolverse en su estado actual o ya tiene una devolución abierta.');
            }

            $estadoAnteriorId = $partidaBloqueada->estado_id;
            $estadoDevuelto = Estado::where('clave', 'DEVUELTO_AREA')->firstOrFail();

            $devolucion = HistorialDevolucion::create([
                'partida_id' => $partidaBloqueada->id,
                'usuario_id' => $usuario->id,
                'estado_anterior_id' => $estadoAnteriorId,
                'observaciones' => $validados['observaciones'],
                'fecha_devolucion' => now(),
                'resultado_cierre' => null,
            ]);

            $partidaBloqueada->update(['estado_id' => $estadoDevuelto->id]);

            $this->recalcularEstadoExpediente($servicio);

            return $devolucion->load(['estadoAnterior', 'usuario']);
        });
    }

    /**
     * Registra la subsanación de una devolución abierta y recupera el estado anterior de la partida (RF-018).
     */
    public function reanudarDesdeDevolucion(Partida $partida, User $usuario, string $respuestaSubsanacion): Partida
    {
        Gate::forUser($usuario)->authorize('reanudar', $partida);

        $validados = Validator::make(
            ['respuesta_subsanacion' => trim($respuestaSubsanacion)],
            ['respuesta_subsanacion' => ['required', 'string', 'max:2000']],
            ['respuesta_subsanacion.required' => 'Debes capturar la respuesta de subsanación.'],
        )->validate();

        return DB::transaction(function () use ($partida, $usuario, $validados): Partida {
            $servicio = Servicio::whereKey($partida->servicio_id)->lockForUpdate()->firstOrFail();
            $partidaBloqueada = Partida::whereKey($partida->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($usuario)->allows('reanudar', $partidaBloqueada)) {
                throw new AuthorizationException('La partida no se encuentra en estado Devuelto al área con devolución abierta.');
            }

            $devolucion = $partidaBloqueada->devoluciones()
                ->whereNull('resultado_cierre')
                ->lockForUpdate()
                ->latest('id')
                ->firstOrFail();

            $estadoRecuperado = Estado::findOrFail($devolucion->estado_anterior_id);
            if (! in_array($estadoRecuperado->clave, ['TURNADO', 'EN_ATENCION', 'EN_REVISION', 'REGISTRADO'], true)) {
                throw ValidationException::withMessages([
                    'respuesta_subsanacion' => 'El estado operativo anterior de la partida no permite reanudar.',
                ]);
            }

            $devolucion->update([
                'respuesta_subsanacion' => $validados['respuesta_subsanacion'],
                'reanudado_por' => $usuario->id,
                'fecha_reanudacion' => now(),
                'resultado_cierre' => 'SUBSANADA',
            ]);

            $partidaBloqueada->update(['estado_id' => $estadoRecuperado->id]);

            $this->recalcularEstadoExpediente($servicio);

            return $partidaBloqueada->fresh(['servicio', 'departamento', 'estado', 'folio']);
        });
    }

    /**
     * Indica si la partida corresponde al flujo especial de entrega de materiales de Adquisiciones (RF-017 / RF-019).
     */
    public function esEntregaAdquisiciones(Partida $partida): bool
    {
        $partida->loadMissing(['departamento', 'partidaOrigen']);

        return strtoupper((string) $partida->departamento?->siglas) === 'ADQ'
            && $partida->partida_origen_id !== null
            && $partida->partidaOrigen !== null
            && (int) $partida->partidaOrigen->servicio_id === (int) $partida->servicio_id;
    }

    /**
     * Concluye una partida en estado EN_ATENCION (RF-019), contemplando tanto servicios operativos
     * (con o sin conformidad + evidencias) como entrega de materiales en Adquisiciones.
     *
     * @param  array<int, UploadedFile>  $evidencias
     */
    public function concluir(
        Partida $partida,
        array $datos,
        User $usuario,
        array $evidencias = [],
        ?UploadedFile $archivoConformidad = null,
    ): RegistroOperativo {
        Gate::forUser($usuario)->authorize('concluir', $partida);

        $esAdqEntrega = $this->esEntregaAdquisiciones($partida);
        $validados = $this->validarDatosConclusion($datos, $evidencias, $archivoConformidad, $esAdqEntrega);

        $rutasGuardadas = [];

        try {
            return DB::transaction(function () use ($partida, $validados, $usuario, $evidencias, $archivoConformidad, $esAdqEntrega, &$rutasGuardadas): RegistroOperativo {
                $servicio = Servicio::whereKey($partida->servicio_id)->lockForUpdate()->firstOrFail();
                $partidaBloqueada = Partida::with(['departamento', 'partidaOrigen', 'estado'])
                    ->whereKey($partida->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! Gate::forUser($usuario)->allows('concluir', $partidaBloqueada)) {
                    throw new AuthorizationException('La partida ya no cumple las condiciones para concluirse.');
                }

                if (! $esAdqEntrega && ! $partidaBloqueada->asignaciones()->exists()) {
                    throw ValidationException::withMessages([
                        'descripcion_trabajo' => 'Para concluir un servicio operativo debe existir al menos una asignación operativa registrada.',
                    ]);
                }

                $ahora = now();
                $fechaConclusion = $partidaBloqueada->fecha_inicio !== null && $ahora->lt($partidaBloqueada->fecha_inicio)
                    ? $partidaBloqueada->fecha_inicio
                    : $ahora;

                $evidenciasMeta = [];
                foreach ($evidencias as $evidencia) {
                    $ext = strtolower($evidencia->getClientOriginalExtension() ?: $evidencia->extension() ?: 'bin');
                    $nombreArchivo = 'EVIDENCIA_'.$partidaBloqueada->id.'_'.Str::uuid().'.'.$ext;
                    $ruta = $evidencia->storeAs("partidas/{$partidaBloqueada->id}/evidencias", $nombreArchivo, self::DISCO_DOCUMENTOS);
                    $rutasGuardadas[] = $ruta;

                    $evidenciasMeta[] = [
                        'ruta' => $ruta,
                        'nombre_original' => $evidencia->getClientOriginalName(),
                        'mime' => $evidencia->getMimeType(),
                        'fecha' => $fechaConclusion->toIso8601String(),
                    ];
                }

                $rutaConformidad = null;
                if (! $esAdqEntrega && $validados['conformidad_recibida'] && $archivoConformidad !== null) {
                    $ext = strtolower($archivoConformidad->getClientOriginalExtension() ?: $archivoConformidad->extension() ?: 'pdf');
                    $nombreConf = 'CONFORMIDAD_'.$partidaBloqueada->id.'_'.Str::uuid().'.'.$ext;
                    $rutaConformidad = $archivoConformidad->storeAs("partidas/{$partidaBloqueada->id}/conformidad", $nombreConf, self::DISCO_DOCUMENTOS);
                    $rutasGuardadas[] = $rutaConformidad;
                }

                $registro = RegistroOperativo::create([
                    'partida_id' => $partidaBloqueada->id,
                    'usuario_id' => $usuario->id,
                    'fecha_conclusion' => $fechaConclusion,
                    'descripcion_trabajo' => $validados['descripcion_trabajo'],
                    'descripcion_materiales' => $validados['descripcion_materiales'],
                    'evidencias' => $evidenciasMeta !== [] ? $evidenciasMeta : null,
                    'conformidad_recibida' => $esAdqEntrega ? false : (bool) $validados['conformidad_recibida'],
                    'recibe_nombre' => (! $esAdqEntrega && $validados['conformidad_recibida']) ? $validados['recibe_nombre'] : null,
                    'recibe_cargo' => (! $esAdqEntrega && $validados['conformidad_recibida']) ? $validados['recibe_cargo'] : null,
                    'archivo_conformidad' => $rutaConformidad,
                    'motivo_sin_conformidad' => (! $esAdqEntrega && ! $validados['conformidad_recibida']) ? $validados['motivo_sin_conformidad'] : null,
                ]);

                $this->finalizarAsignacionesActivas($partidaBloqueada, $fechaConclusion);

                $estadoConcluido = Estado::where('clave', 'CONCLUIDO')->firstOrFail();
                $actualizacionPartida = [
                    'estado_id' => $estadoConcluido->id,
                    'fecha_conclusion' => $fechaConclusion,
                ];

                if ($esAdqEntrega) {
                    $actualizacionPartida['nombre_quien_recibe_material'] = $validados['nombre_quien_recibe_material'];
                } elseif ($validados['cantidad_atendida'] !== null) {
                    $actualizacionPartida['cantidad_atendida'] = $validados['cantidad_atendida'];
                }

                $partidaBloqueada->update($actualizacionPartida);

                $this->recalcularEstadoExpediente($servicio);

                return $registro->load(['usuario', 'partida.estado']);
            });
        } catch (Throwable $exception) {
            foreach ($rutasGuardadas as $ruta) {
                Storage::disk(self::DISCO_DOCUMENTOS)->delete($ruta);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, UploadedFile>  $evidencias
     * @return array<string, mixed>
     */
    private function validarDatosConclusion(array $datos, array $evidencias, ?UploadedFile $archivoConformidad, bool $esAdqEntrega): array
    {
        if ($esAdqEntrega) {
            return Validator::make([
                'nombre_quien_recibe_material' => trim((string) ($datos['nombre_quien_recibe_material'] ?? '')),
                'descripcion_trabajo' => trim((string) ($datos['descripcion_trabajo'] ?? '')),
                'descripcion_materiales' => $this->textoOpcional($datos['descripcion_materiales'] ?? null),
                'evidencias' => $evidencias,
            ], [
                'nombre_quien_recibe_material' => ['required', 'string', 'max:255'],
                'descripcion_trabajo' => ['required', 'string', 'max:3000'],
                'descripcion_materiales' => ['nullable', 'string', 'max:3000'],
                'evidencias' => ['array', 'max:10'],
                'evidencias.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ], [
                'nombre_quien_recibe_material.required' => 'Debes registrar el nombre de la persona que recibió los materiales.',
                'descripcion_trabajo.required' => 'Debes registrar la descripción de la entrega realizada.',
            ])->validate();
        }

        $conformidad = (bool) ($datos['conformidad_recibida'] ?? false);

        $validados = Validator::make([
            'descripcion_trabajo' => trim((string) ($datos['descripcion_trabajo'] ?? '')),
            'descripcion_materiales' => $this->textoOpcional($datos['descripcion_materiales'] ?? null),
            'cantidad_atendida' => ($datos['cantidad_atendida'] ?? '') !== '' ? $datos['cantidad_atendida'] : null,
            'conformidad_recibida' => $conformidad,
            'recibe_nombre' => $this->textoOpcional($datos['recibe_nombre'] ?? null),
            'recibe_cargo' => $this->textoOpcional($datos['recibe_cargo'] ?? null),
            'motivo_sin_conformidad' => $this->textoOpcional($datos['motivo_sin_conformidad'] ?? null),
            'evidencias' => $evidencias,
            'archivo_conformidad' => $archivoConformidad,
        ], [
            'descripcion_trabajo' => ['required', 'string', 'max:3000'],
            'descripcion_materiales' => ['nullable', 'string', 'max:3000'],
            'cantidad_atendida' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'conformidad_recibida' => ['required', 'boolean'],
            'recibe_nombre' => [$conformidad ? 'required' : 'nullable', 'string', 'max:255'],
            'recibe_cargo' => [$conformidad ? 'required' : 'nullable', 'string', 'max:255'],
            'motivo_sin_conformidad' => [$conformidad ? 'nullable' : 'required', 'string', 'max:2000'],
            'evidencias' => ['array', 'max:10'],
            'evidencias.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'archivo_conformidad' => [$conformidad ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'descripcion_trabajo.required' => 'Debes capturar la descripción de los trabajos realizados.',
            'recibe_nombre.required' => 'Debes indicar el nombre de quien otorgó la conformidad.',
            'recibe_cargo.required' => 'Debes indicar el cargo de quien otorgó la conformidad.',
            'archivo_conformidad.required' => 'Debes adjuntar el documento firmado de conformidad.',
            'motivo_sin_conformidad.required' => 'Debes justificar por qué no se obtuvo la conformidad.',
            'evidencias.*.mimes' => 'Las evidencias deben ser archivos PDF, JPG o PNG.',
            'archivo_conformidad.mimes' => 'El archivo de conformidad debe ser PDF, JPG o PNG.',
        ])->validate();

        if (! $conformidad && count($evidencias) === 0) {
            throw ValidationException::withMessages([
                'evidencias' => 'Para concluir sin conformidad debes adjuntar al menos una evidencia documental o fotográfica.',
            ]);
        }

        return $validados;
    }

    /**
     * Recalcula servicios.estado_id siguiendo el orden exacto de precedencia de RF-015.
     */
    public function recalcularEstadoExpediente(Servicio $servicio): void
    {
        $claves = $servicio->partidas()
            ->join('cat_estados', 'cat_estados.id', '=', 'partidas.estado_id')
            ->pluck('cat_estados.clave');

        if ($claves->isEmpty()) {
            return;
        }

        $terminales = ['CONCLUIDO', 'CANCELADO'];
        $noTerminales = $claves->reject(fn (string $clave): bool => in_array($clave, $terminales, true));

        $claveDestino = match (true) {
            $noTerminales->isEmpty() && $claves->contains('CONCLUIDO') => 'CONCLUIDO',
            $claves->every(fn (string $clave): bool => $clave === 'CANCELADO') => 'CANCELADO',
            $claves->contains('EN_ATENCION') => 'EN_ATENCION',
            $noTerminales->isNotEmpty() && $noTerminales->every(fn (string $clave): bool => $clave === 'DEVUELTO_AREA') => 'DEVUELTO_AREA',
            $claves->contains('TURNADO') => 'TURNADO',
            $claves->contains('EN_REVISION') => 'EN_REVISION',
            default => 'REGISTRADO',
        };

        $estadoId = Estado::where('clave', $claveDestino)->value('id');
        if ($estadoId !== null && (int) $servicio->estado_id !== (int) $estadoId) {
            $servicio->update(['estado_id' => $estadoId]);
        }
    }

    private function validarInformacionObligatoriaAntesDeTurnar(Partida $partida): void
    {
        $errores = [];

        if ($partida->tipo_servicio_id === null) {
            $errores['partida'] = 'Antes de turnar debes capturar el tipo de servicio de la partida.';
        }

        if (trim((string) $partida->concepto) === '') {
            $errores['partida'] = 'Antes de turnar debes capturar el concepto de la partida.';
        }

        if ($partida->tipo_servicio_id !== null) {
            $tipo = TipoServicio::find($partida->tipo_servicio_id);
            if ($tipo === null || ! $tipo->activo) {
                $errores['partida'] = 'El tipo de servicio asignado a la partida no es válido o está inactivo.';
            } elseif ($tipo->requiere_cantidad) {
                if ($partida->cantidad_solicitada === null || (float) $partida->cantidad_solicitada <= 0) {
                    $errores['partida'] = 'Este tipo de servicio requiere cantidad solicitada mayor a cero antes de turnar.';
                } elseif (trim((string) $partida->unidad_medida) === '') {
                    $errores['partida'] = 'Este tipo de servicio requiere capturar la unidad de medida antes de turnar.';
                }
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    private function finalizarAsignacionesActivas(Partida $partida, CarbonInterface $ahora): void
    {
        $partida->asignaciones()
            ->where('activo', true)
            ->whereNull('fecha_finalizacion')
            ->lockForUpdate()
            ->get()
            ->each(function (OperativoPartida $asignacion) use ($ahora): void {
                $fechaFinal = $asignacion->fecha_asignacion !== null && $ahora->lt($asignacion->fecha_asignacion)
                    ? $asignacion->fecha_asignacion
                    : $ahora;

                $asignacion->update([
                    'activo' => false,
                    'fecha_finalizacion' => $fechaFinal,
                ]);
            });
    }

    private function obtenerOGenerarFolio(Servicio $servicio, Departamento $destino, CarbonInterface $fecha): ServicioFolio
    {
        $folioExistente = ServicioFolio::where('servicio_id', $servicio->id)
            ->where('departamento_id', $destino->id)
            ->lockForUpdate()
            ->first();

        if ($folioExistente !== null) {
            return $folioExistente;
        }

        $ejercicio = (int) $fecha->year;
        $mes = (int) $fecha->month;

        $consecutivo = ConsecutivoFolio::where('departamento_id', $destino->id)
            ->where('ejercicio', $ejercicio)
            ->lockForUpdate()
            ->first();

        if ($consecutivo === null) {
            try {
                ConsecutivoFolio::create([
                    'departamento_id' => $destino->id,
                    'ejercicio' => $ejercicio,
                    'ultimo_numero' => 0,
                ]);
            } catch (QueryException) {
                // Otro proceso creó el registro inicial del año de forma concurrente.
            }

            $consecutivo = ConsecutivoFolio::where('departamento_id', $destino->id)
                ->where('ejercicio', $ejercicio)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $nuevoNumero = ((int) $consecutivo->ultimo_numero) + 1;
        $consecutivo->update(['ultimo_numero' => $nuevoNumero]);

        // RF-010: SIGLA/MES/CONSECUTIVO/AA (últimos dos dígitos del año).
        $textoFolio = sprintf(
            '%s/%02d/%04d/%s',
            strtoupper((string) $destino->siglas),
            $mes,
            $nuevoNumero,
            $fecha->format('y')
        );

        return ServicioFolio::create([
            'servicio_id' => $servicio->id,
            'departamento_id' => $destino->id,
            'ejercicio' => $ejercicio,
            'mes' => $mes,
            'consecutivo' => $nuevoNumero,
            'folio' => $textoFolio,
            'fecha_asignacion' => $fecha,
        ]);
    }

    private function textoOpcional(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }
}
