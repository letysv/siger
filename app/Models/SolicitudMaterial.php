<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudMaterial extends Model
{
    protected $table = 'solicitudes_materiales';

    protected $fillable = ['partida_id', 'usuario_solicitante_id', 'descripcion', 'cantidad', 'unidad_medida', 'justificacion', 'estado', 'fecha_envio', 'usuario_resolucion_id', 'fecha_resolucion', 'observaciones_direccion', 'partida_generada_id'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'fecha_envio' => 'datetime',
            'fecha_resolucion' => 'datetime',
        ];
    }

    public function partidaOriginal(): BelongsTo { return $this->belongsTo(Partida::class, 'partida_id'); }

    public function partidaGenerada(): BelongsTo { return $this->belongsTo(Partida::class, 'partida_generada_id'); }

    public function solicitante(): BelongsTo { return $this->belongsTo(User::class, 'usuario_solicitante_id'); }

    public function resolutor(): BelongsTo { return $this->belongsTo(User::class, 'usuario_resolucion_id'); }

    public function historial(): HasMany { return $this->hasMany(HistorialSolicitudMaterial::class, 'solicitud_material_id'); }
}
