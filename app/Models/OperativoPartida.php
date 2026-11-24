<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OperativoPartida extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'operativos_partida';

    protected $fillable = ['partida_id', 'operativo_id', 'fecha_asignacion', 'fecha_finalizacion', 'activo'];

    protected function casts(): array
    {
        return [
            'fecha_asignacion' => 'datetime',
            'fecha_finalizacion' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('asignaciones')
            ->logOnly(['partida_id', 'operativo_id', 'fecha_asignacion', 'fecha_finalizacion', 'activo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function partida(): BelongsTo { return $this->belongsTo(Partida::class, 'partida_id'); }

    public function operativo(): BelongsTo { return $this->belongsTo(Operativo::class, 'operativo_id'); }
}
