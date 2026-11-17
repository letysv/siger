<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HistorialSolicitudMaterial extends Model
{
    protected $table = 'historial_solicitudes_materiales';

    protected $fillable = ['solicitud_material_id', 'usuario_id', 'accion', 'estado_anterior', 'estado_nuevo', 'observaciones', 'fecha'];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo { return $this->belongsTo(SolicitudMaterial::class, 'solicitud_material_id'); }

    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'usuario_id'); }
}
