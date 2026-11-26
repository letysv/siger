<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class HistorialSolicitudMaterial extends Model
{
    use LogsActivity;

    protected $table = 'historial_solicitudes_materiales';

    protected $fillable = ['solicitud_material_id', 'usuario_id', 'accion', 'estado_anterior', 'estado_nuevo', 'observaciones', 'fecha'];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('solicitudes_materiales')
            ->logOnly([
                'solicitud_material_id',
                'usuario_id',
                'accion',
                'estado_anterior',
                'estado_nuevo',
                'observaciones',
                'fecha',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function solicitud(): BelongsTo { return $this->belongsTo(SolicitudMaterial::class, 'solicitud_material_id'); }

    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'usuario_id'); }
}
