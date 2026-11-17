<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServicioFolio extends Model
{
    protected $table = 'servicio_folios';

    protected $fillable = ['servicio_id', 'departamento_id', 'ejercicio', 'mes', 'consecutivo', 'folio', 'fecha_asignacion'];

    protected function casts(): array
    {
        return [
            'fecha_asignacion' => 'datetime',
        ];
    }

    public function servicio(): BelongsTo { return $this->belongsTo(Servicio::class, 'servicio_id'); }

    public function departamento(): BelongsTo { return $this->belongsTo(Departamento::class, 'departamento_id'); }

    public function partidas(): HasMany { return $this->hasMany(Partida::class, 'servicio_folio_id'); }
}
