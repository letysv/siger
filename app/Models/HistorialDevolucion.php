<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HistorialDevolucion extends Model
{
    protected $table = 'historial_devoluciones';

    protected $fillable = ['partida_id', 'usuario_id', 'estado_anterior_id', 'observaciones', 'fecha_devolucion', 'respuesta_subsanacion', 'reanudado_por', 'fecha_reanudacion', 'resultado_cierre', 'cerrado_por', 'fecha_cierre'];

    protected function casts(): array
    {
        return [
            'fecha_devolucion' => 'datetime',
            'fecha_reanudacion' => 'datetime',
            'fecha_cierre' => 'datetime',
        ];
    }

    public function partida(): BelongsTo { return $this->belongsTo(Partida::class, 'partida_id'); }

    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'usuario_id'); }

    public function estadoAnterior(): BelongsTo { return $this->belongsTo(Estado::class, 'estado_anterior_id'); }

    public function usuarioReanudacion(): BelongsTo { return $this->belongsTo(User::class, 'reanudado_por'); }

    public function usuarioCierre(): BelongsTo { return $this->belongsTo(User::class, 'cerrado_por'); }
}
