<?php

namespace App\Services;

use App\Models\ConsecutivoFolio;
use App\Models\Departamento;
use App\Models\Estado;
use App\Models\HistorialTurnado;
use App\Models\OperativoPartida;
use App\Models\Partida;
use App\Models\Servicio;
use App\Models\ServicioFolio;
use App\Models\TipoServicio;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Lógica de negocio y controles transaccionales de partidas, turnados y folios (RF-010, RF-013, RF-014, RF-015, RF-026).
 */
class PartidaService
{
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
