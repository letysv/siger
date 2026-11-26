<?php

namespace App\Services;

use App\Models\Departamento;
use App\Models\Estado;
use App\Models\HistorialSolicitudMaterial;
use App\Models\Partida;
use App\Models\Servicio;
use App\Models\SolicitudMaterial;
use App\Models\TipoServicio;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Lógica de negocio y transacciones para solicitudes de materiales y partidas adicionales ADQ (RF-017).
 */
class SolicitudMaterialService
{
    public function __construct(
        private readonly PartidaService $partidaService,
    ) {}

    /**
     * Consulta acotada al alcance del usuario autenticado (Dirección sin borradores, Jefe de su departamento).
     *
     * @return Builder<SolicitudMaterial>
     */
    public function queryVisiblesPara(User $usuario): Builder
    {
        if (! $usuario->activo || ! $usuario->checkPermissionTo('solicitudes_materiales.ver', 'web')) {
            return SolicitudMaterial::query()->whereRaw('1 = 0');
        }

        if ($usuario->hasAnyRole(['Super usuario', 'Administrador'])) {
            return SolicitudMaterial::query()->whereIn('estado', [
                'ENVIADA',
                'INFORMACION_REQUERIDA',
                'AUTORIZADA',
                'RECHAZADA',
            ]);
        }

        if ($usuario->hasRole('Jefe') && $usuario->departamento_id !== null) {
            return SolicitudMaterial::query()->whereHas(
                'partidaOriginal',
                fn (Builder $q) => $q->where('departamento_id', $usuario->departamento_id)
            );
        }

        return SolicitudMaterial::query()->whereRaw('1 = 0');
    }

    /**
     * Registra una nueva solicitud de materiales en estado BORRADOR y su primer registro histórico (CREACION).
     */
    public function crearBorrador(Partida $partidaOriginal, array $datos, User $jefe): SolicitudMaterial
    {
        Gate::forUser($jefe)->authorize('create', [SolicitudMaterial::class, $partidaOriginal]);

        $validados = $this->validarDatosSolicitud($datos);

        return DB::transaction(function () use ($partidaOriginal, $validados, $jefe): SolicitudMaterial {
            $partidaBloqueada = Partida::whereKey($partidaOriginal->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($jefe)->allows('create', [SolicitudMaterial::class, $partidaBloqueada])) {
                throw new AuthorizationException('La partida original ya no permite registrar solicitudes de materiales.');
            }

            $ahora = now();

            $solicitud = SolicitudMaterial::create([
                'partida_id' => $partidaBloqueada->id,
                'usuario_solicitante_id' => $jefe->id,
                'descripcion' => $validados['descripcion'],
                'cantidad' => $validados['cantidad'],
                'unidad_medida' => $validados['unidad_medida'],
                'justificacion' => $validados['justificacion'],
                'estado' => 'BORRADOR',
            ]);

            HistorialSolicitudMaterial::create([
                'solicitud_material_id' => $solicitud->id,
                'usuario_id' => $jefe->id,
                'accion' => 'CREACION',
                'estado_anterior' => null,
                'estado_nuevo' => 'BORRADOR',
                'observaciones' => null,
                'fecha' => $ahora,
            ]);

            return $solicitud->load(['partidaOriginal.departamento', 'partidaOriginal.folio', 'solicitante']);
        });
    }

    /**
     * Modifica una solicitud en estado BORRADOR o INFORMACION_REQUERIDA por su Jefe autor.
     */
    public function actualizar(SolicitudMaterial $solicitud, array $datos, User $jefe): SolicitudMaterial
    {
        Gate::forUser($jefe)->authorize('update', $solicitud);

        $validados = $this->validarDatosSolicitud($datos);

        return DB::transaction(function () use ($solicitud, $validados, $jefe): SolicitudMaterial {
            $bloqueada = SolicitudMaterial::with('partidaOriginal')->whereKey($solicitud->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($jefe)->allows('update', $bloqueada)) {
                throw new AuthorizationException('La solicitud ya no puede modificarse en su estado actual.');
            }

            $bloqueada->update([
                'descripcion' => $validados['descripcion'],
                'cantidad' => $validados['cantidad'],
                'unidad_medida' => $validados['unidad_medida'],
                'justificacion' => $validados['justificacion'],
            ]);

            return $bloqueada->fresh(['partidaOriginal', 'solicitante']);
        });
    }

    /**
     * Envía (o reenvía con respuesta de aclaración) la solicitud a Dirección.
     */
    public function enviar(SolicitudMaterial $solicitud, User $jefe, ?string $respuestaAclaracion = null): SolicitudMaterial
    {
        Gate::forUser($jefe)->authorize('enviar', $solicitud);

        $esRespuesta = $solicitud->estado === 'INFORMACION_REQUERIDA';
        $respuesta = $this->textoOpcional($respuestaAclaracion);

        if ($esRespuesta && $respuesta === null) {
            throw ValidationException::withMessages([
                'respuesta_aclaracion' => 'Debes capturar la respuesta a las observaciones de Dirección antes de reenviar.',
            ]);
        }

        return DB::transaction(function () use ($solicitud, $jefe, $respuesta): SolicitudMaterial {
            $bloqueada = SolicitudMaterial::with('partidaOriginal')->whereKey($solicitud->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($jefe)->allows('enviar', $bloqueada)) {
                throw new AuthorizationException('La solicitud ya fue enviada o no puede enviarse en su estado actual.');
            }

            $this->validarDatosSolicitud([
                'descripcion' => $bloqueada->descripcion,
                'cantidad' => $bloqueada->cantidad,
                'unidad_medida' => $bloqueada->unidad_medida,
                'justificacion' => $bloqueada->justificacion,
            ]);

            $estadoAnterior = $bloqueada->estado;
            $accion = $estadoAnterior === 'INFORMACION_REQUERIDA' ? 'RESPUESTA' : 'ENVIO';
            $ahora = now();

            $bloqueada->update([
                'estado' => 'ENVIADA',
                'fecha_envio' => $ahora,
            ]);

            HistorialSolicitudMaterial::create([
                'solicitud_material_id' => $bloqueada->id,
                'usuario_id' => $jefe->id,
                'accion' => $accion,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => 'ENVIADA',
                'observaciones' => $accion === 'RESPUESTA' ? $respuesta : null,
                'fecha' => $ahora,
            ]);

            return $bloqueada->fresh(['partidaOriginal', 'solicitante', 'historial']);
        });
    }

    /**
     * Dirección solicita información complementaria al Jefe solicitante.
     */
    public function solicitarInformacion(SolicitudMaterial $solicitud, User $direccion, string $observaciones): SolicitudMaterial
    {
        Gate::forUser($direccion)->authorize('solicitarInformacion', $solicitud);

        $validados = Validator::make(
            ['observaciones_direccion' => trim($observaciones)],
            ['observaciones_direccion' => ['required', 'string', 'max:2000']],
            ['observaciones_direccion.required' => 'Debes indicar qué información complementaria se requiere.'],
        )->validate();

        return DB::transaction(function () use ($solicitud, $direccion, $validados): SolicitudMaterial {
            $bloqueada = SolicitudMaterial::with('partidaOriginal')->whereKey($solicitud->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($direccion)->allows('solicitarInformacion', $bloqueada)) {
                throw new AuthorizationException('La solicitud ya no admite requerimiento de información.');
            }

            $ahora = now();
            $bloqueada->update([
                'estado' => 'INFORMACION_REQUERIDA',
                'observaciones_direccion' => $validados['observaciones_direccion'],
            ]);

            HistorialSolicitudMaterial::create([
                'solicitud_material_id' => $bloqueada->id,
                'usuario_id' => $direccion->id,
                'accion' => 'SOLICITUD_INFORMACION',
                'estado_anterior' => 'ENVIADA',
                'estado_nuevo' => 'INFORMACION_REQUERIDA',
                'observaciones' => $validados['observaciones_direccion'],
                'fecha' => $ahora,
            ]);

            return $bloqueada->fresh(['partidaOriginal', 'solicitante', 'historial']);
        });
    }

    /**
     * Dirección rechaza la solicitud registrando obligatoriamente el motivo.
     */
    public function rechazar(SolicitudMaterial $solicitud, User $direccion, string $motivo): SolicitudMaterial
    {
        Gate::forUser($direccion)->authorize('rechazar', $solicitud);

        $validados = Validator::make(
            ['observaciones_direccion' => trim($motivo)],
            ['observaciones_direccion' => ['required', 'string', 'max:2000']],
            ['observaciones_direccion.required' => 'Debes indicar el motivo del rechazo.'],
        )->validate();

        return DB::transaction(function () use ($solicitud, $direccion, $validados): SolicitudMaterial {
            $bloqueada = SolicitudMaterial::with('partidaOriginal')->whereKey($solicitud->getKey())->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($direccion)->allows('rechazar', $bloqueada)) {
                throw new AuthorizationException('La solicitud ya no puede rechazarse en su estado actual.');
            }

            $ahora = now();
            $bloqueada->update([
                'estado' => 'RECHAZADA',
                'usuario_resolucion_id' => $direccion->id,
                'fecha_resolucion' => $ahora,
                'observaciones_direccion' => $validados['observaciones_direccion'],
            ]);

            HistorialSolicitudMaterial::create([
                'solicitud_material_id' => $bloqueada->id,
                'usuario_id' => $direccion->id,
                'accion' => 'RECHAZO',
                'estado_anterior' => 'ENVIADA',
                'estado_nuevo' => 'RECHAZADA',
                'observaciones' => $validados['observaciones_direccion'],
                'fecha' => $ahora,
            ]);

            return $bloqueada->fresh(['partidaOriginal', 'solicitante', 'resolutor', 'historial']);
        });
    }

    /**
     * Dirección autoriza la solicitud y crea en una sola transacción la partida adicional en REGISTRADO
     * destinada a Adquisiciones (sin folio aún), vinculada por partida_origen_id y partida_generada_id (RF-017).
     */
    public function autorizar(SolicitudMaterial $solicitud, array $datosPartida, User $direccion): SolicitudMaterial
    {
        Gate::forUser($direccion)->authorize('autorizar', $solicitud);

        $normalizados = [
            'tipo_servicio_id' => $this->enteroOpcional($datosPartida['tipo_servicio_id'] ?? null),
            'concepto' => trim((string) ($datosPartida['concepto'] ?? '')),
            'cantidad_solicitada' => ($datosPartida['cantidad_solicitada'] ?? '') !== '' ? $datosPartida['cantidad_solicitada'] : null,
            'unidad_medida' => $this->textoOpcional($datosPartida['unidad_medida'] ?? null),
            'observaciones_direccion' => $this->textoOpcional($datosPartida['observaciones_direccion'] ?? null),
        ];

        $validados = Validator::make($normalizados, [
            'tipo_servicio_id' => ['required', 'integer', 'exists:cat_tipo_servicios,id'],
            'concepto' => ['required', 'string', 'max:2000'],
            'cantidad_solicitada' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'unidad_medida' => ['nullable', 'string', 'max:100'],
            'observaciones_direccion' => ['nullable', 'string', 'max:2000'],
        ], [
            'tipo_servicio_id.required' => 'Debes seleccionar el tipo de servicio para la partida de Adquisiciones.',
            'concepto.required' => 'Debes capturar el concepto de la nueva partida para Adquisiciones.',
        ])->validate();

        if ($validados['tipo_servicio_id'] !== null) {
            $tipo = TipoServicio::findOrFail($validados['tipo_servicio_id']);
            if (! $tipo->activo) {
                throw ValidationException::withMessages([
                    'tipo_servicio_id' => 'El tipo de servicio seleccionado está inactivo.',
                ]);
            }

            if ($tipo->requiere_cantidad) {
                if ($validados['cantidad_solicitada'] === null || (float) $validados['cantidad_solicitada'] <= 0) {
                    throw ValidationException::withMessages([
                        'cantidad_solicitada' => 'El tipo de servicio requiere una cantidad solicitada mayor a cero.',
                    ]);
                }
                if ($validados['unidad_medida'] === null) {
                    throw ValidationException::withMessages([
                        'unidad_medida' => 'El tipo de servicio requiere capturar la unidad de medida.',
                    ]);
                }
            }
        }

        return DB::transaction(function () use ($solicitud, $validados, $direccion): SolicitudMaterial {
            $bloqueada = SolicitudMaterial::whereKey($solicitud->getKey())->lockForUpdate()->firstOrFail();
            $partidaOriginal = Partida::whereKey($bloqueada->partida_id)->lockForUpdate()->firstOrFail();
            $servicio = Servicio::whereKey($partidaOriginal->servicio_id)->lockForUpdate()->firstOrFail();

            if (! Gate::forUser($direccion)->allows('autorizar', $bloqueada) || $bloqueada->partida_generada_id !== null) {
                throw new AuthorizationException('La solicitud ya fue resuelta o no puede autorizarse en su estado actual.');
            }

            $deptoAdq = Departamento::where('siglas', 'ADQ')->where('activo', true)->firstOrFail();
            $estadoRegistrado = Estado::where('clave', 'REGISTRADO')->firstOrFail();
            $siguienteNumero = ((int) $servicio->partidas()->max('numero_partida')) + 1;
            $ahora = now();

            $partidaNueva = Partida::create([
                'servicio_id' => $servicio->id,
                'partida_origen_id' => $partidaOriginal->id,
                'numero_partida' => $siguienteNumero,
                'servicio_folio_id' => null,
                'tipo_servicio_id' => $validados['tipo_servicio_id'],
                'concepto' => $validados['concepto'],
                'cantidad_solicitada' => $validados['cantidad_solicitada'],
                'cantidad_atendida' => null,
                'unidad_medida' => $validados['unidad_medida'],
                'departamento_id' => $deptoAdq->id,
                'observaciones' => $validados['observaciones_direccion'],
                'estado_id' => $estadoRegistrado->id,
            ]);

            if ((int) $partidaNueva->servicio_id !== (int) $partidaOriginal->servicio_id
                || (int) $partidaNueva->partida_origen_id !== (int) $bloqueada->partida_id) {
                throw new \RuntimeException('Inconsistencia de integridad entre partida original y partida generada.');
            }

            $bloqueada->update([
                'estado' => 'AUTORIZADA',
                'usuario_resolucion_id' => $direccion->id,
                'fecha_resolucion' => $ahora,
                'observaciones_direccion' => $validados['observaciones_direccion'],
                'partida_generada_id' => $partidaNueva->id,
            ]);

            HistorialSolicitudMaterial::create([
                'solicitud_material_id' => $bloqueada->id,
                'usuario_id' => $direccion->id,
                'accion' => 'AUTORIZACION',
                'estado_anterior' => 'ENVIADA',
                'estado_nuevo' => 'AUTORIZADA',
                'observaciones' => $validados['observaciones_direccion'],
                'fecha' => $ahora,
            ]);

            $this->partidaService->recalcularEstadoExpediente($servicio);

            return $bloqueada->fresh([
                'partidaOriginal.departamento',
                'partidaGenerada.estado',
                'partidaGenerada.folio',
                'solicitante',
                'resolutor',
                'historial',
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function validarDatosSolicitud(array $datos): array
    {
        $cantidad = ($datos['cantidad'] ?? '') !== '' ? $datos['cantidad'] : null;
        $unidad = $this->textoOpcional($datos['unidad_medida'] ?? null);

        $validados = Validator::make([
            'descripcion' => trim((string) ($datos['descripcion'] ?? '')),
            'cantidad' => $cantidad,
            'unidad_medida' => $unidad,
            'justificacion' => trim((string) ($datos['justificacion'] ?? '')),
        ], [
            'descripcion' => ['required', 'string', 'max:3000'],
            'cantidad' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'unidad_medida' => ['nullable', 'string', 'max:100'],
            'justificacion' => ['required', 'string', 'max:3000'],
        ], [
            'descripcion.required' => 'Debes describir los materiales, insumos o recursos requeridos.',
            'justificacion.required' => 'Debes indicar la justificación de la solicitud.',
            'cantidad.min' => 'La cantidad debe ser mayor a cero.',
        ])->validate();

        if ($validados['cantidad'] !== null && $validados['unidad_medida'] === null) {
            throw ValidationException::withMessages([
                'unidad_medida' => 'Cuando capturas una cantidad debes especificar la unidad de medida.',
            ]);
        }

        return $validados;
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
