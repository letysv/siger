<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OperativoPartida extends Model
{
    use HasFactory;

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

    public function partida(): BelongsTo { return $this->belongsTo(Partida::class, 'partida_id'); }

    public function operativo(): BelongsTo { return $this->belongsTo(Operativo::class, 'operativo_id'); }
}
