<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Partida extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'partidas';

    protected $fillable = ['servicio_id', 'partida_origen_id', 'numero_partida', 'servicio_folio_id', 'tipo_servicio_id', 'concepto', 'cantidad_solicitada', 'cantidad_atendida', 'unidad_medida', 'departamento_id', 'observaciones', 'nombre_quien_recibe_material', 'estado_id', 'fecha_inicio', 'fecha_conclusion', 'motivo_cancelacion', 'cancelado_por', 'fecha_cancelacion'];

    protected function casts(): array
    {
        return [
            'cantidad_solicitada' => 'decimal:2',
            'cantidad_atendida' => 'decimal:2',
            'fecha_inicio' => 'datetime',
            'fecha_conclusion' => 'datetime',
            'fecha_cancelacion' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('partidas')
            ->logOnly([
                'numero_partida',
                'servicio_folio_id',
                'tipo_servicio_id',
                'concepto',
                'cantidad_solicitada',
                'cantidad_atendida',
                'unidad_medida',
                'departamento_id',
                'estado_id',
                'fecha_inicio',
                'fecha_conclusion',
                'motivo_cancelacion',
                'cancelado_por',
                'fecha_cancelacion',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function servicio(): BelongsTo { return $this->belongsTo(Servicio::class, 'servicio_id'); }

    public function partidaOrigen(): BelongsTo { return $this->belongsTo(self::class, 'partida_origen_id'); }

    public function partidasDerivadas(): HasMany { return $this->hasMany(self::class, 'partida_origen_id'); }

    public function folio(): BelongsTo { return $this->belongsTo(ServicioFolio::class, 'servicio_folio_id'); }

    public function tipoServicio(): BelongsTo { return $this->belongsTo(TipoServicio::class, 'tipo_servicio_id'); }

    public function departamento(): BelongsTo { return $this->belongsTo(Departamento::class, 'departamento_id'); }

    public function estado(): BelongsTo { return $this->belongsTo(Estado::class, 'estado_id'); }

    public function usuarioCancelacion(): BelongsTo { return $this->belongsTo(User::class, 'cancelado_por'); }

    public function turnados(): HasMany { return $this->hasMany(HistorialTurnado::class, 'partida_id'); }

    public function asignaciones(): HasMany { return $this->hasMany(OperativoPartida::class, 'partida_id'); }

    public function solicitudesMateriales(): HasMany { return $this->hasMany(SolicitudMaterial::class, 'partida_id'); }

    public function solicitudQueGenero(): HasOne { return $this->hasOne(SolicitudMaterial::class, 'partida_generada_id'); }

    public function devoluciones(): HasMany { return $this->hasMany(HistorialDevolucion::class, 'partida_id'); }

    public function registroConclusion(): HasOne { return $this->hasOne(RegistroOperativo::class, 'partida_id'); }
}
