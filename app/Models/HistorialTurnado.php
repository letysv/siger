<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class HistorialTurnado extends Model
{
    use LogsActivity;

    protected $table = 'historial_turnados';

    protected $fillable = ['partida_id', 'departamento_origen_id', 'departamento_destino_id', 'servicio_folio_origen_id', 'servicio_folio_destino_id', 'usuario_id', 'motivo', 'fecha'];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('partidas')
            ->logOnly([
                'partida_id',
                'departamento_origen_id',
                'departamento_destino_id',
                'servicio_folio_origen_id',
                'servicio_folio_destino_id',
                'usuario_id',
                'motivo',
                'fecha',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function partida(): BelongsTo { return $this->belongsTo(Partida::class, 'partida_id'); }

    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'usuario_id'); }

    public function departamentoOrigen(): BelongsTo { return $this->belongsTo(Departamento::class, 'departamento_origen_id'); }

    public function departamentoDestino(): BelongsTo { return $this->belongsTo(Departamento::class, 'departamento_destino_id'); }

    public function folioOrigen(): BelongsTo { return $this->belongsTo(ServicioFolio::class, 'servicio_folio_origen_id'); }

    public function folioDestino(): BelongsTo { return $this->belongsTo(ServicioFolio::class, 'servicio_folio_destino_id'); }
}
