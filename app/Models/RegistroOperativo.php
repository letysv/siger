<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RegistroOperativo extends Model
{
    protected $table = 'registro_operativo';

    protected $fillable = ['partida_id', 'usuario_id', 'fecha_conclusion', 'descripcion_trabajo', 'descripcion_materiales', 'evidencias', 'conformidad_recibida', 'recibe_nombre', 'recibe_cargo', 'archivo_conformidad', 'motivo_sin_conformidad'];

    protected function casts(): array
    {
        return [
            'fecha_conclusion' => 'datetime',
            'evidencias' => 'array',
            'conformidad_recibida' => 'boolean',
        ];
    }

    public function partida(): BelongsTo { return $this->belongsTo(Partida::class, 'partida_id'); }

    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'usuario_id'); }
}
