<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Servicio extends Model
{
    use HasFactory;

    protected $table = 'servicios';

    protected $fillable = ['numero_oficio', 'fecha_oficio', 'fecha_recepcion', 'ejercicio', 'area_solicitante_id', 'nombre_solicitante', 'dirigido_a', 'asunto', 'descripcion', 'es_via_rapida', 'estado_id', 'usuario_id'];

    protected function casts(): array
    {
        return [
            'fecha_oficio' => 'date',
            'fecha_recepcion' => 'datetime',
            'es_via_rapida' => 'boolean',
        ];
    }

    public function areaSolicitante(): BelongsTo { return $this->belongsTo(Area::class, 'area_solicitante_id'); }

    public function estado(): BelongsTo { return $this->belongsTo(Estado::class, 'estado_id'); }

    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'usuario_id'); }

    public function partidas(): HasMany { return $this->hasMany(Partida::class, 'servicio_id'); }

    public function folios(): HasMany { return $this->hasMany(ServicioFolio::class, 'servicio_id'); }

    public function archivos(): HasMany { return $this->hasMany(ServicioArchivo::class, 'servicio_id'); }

    public function acuses(): HasMany { return $this->hasMany(ServicioAcuse::class, 'servicio_id'); }
}
