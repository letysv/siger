<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ServicioFolio extends Model
{
    use LogsActivity;

    protected $table = 'servicio_folios';

    protected $fillable = ['servicio_id', 'departamento_id', 'ejercicio', 'mes', 'consecutivo', 'folio', 'fecha_asignacion'];

    protected function casts(): array
    {
        return [
            'fecha_asignacion' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('folios')
            ->logOnly(['servicio_id', 'departamento_id', 'ejercicio', 'mes', 'consecutivo', 'folio', 'fecha_asignacion'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function servicio(): BelongsTo { return $this->belongsTo(Servicio::class, 'servicio_id'); }

    public function departamento(): BelongsTo { return $this->belongsTo(Departamento::class, 'departamento_id'); }

    public function partidas(): HasMany { return $this->hasMany(Partida::class, 'servicio_folio_id'); }
}
