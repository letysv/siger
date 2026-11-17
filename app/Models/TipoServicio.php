<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TipoServicio extends Model
{
    protected $table = 'cat_tipo_servicios';

    protected $fillable = ['descripcion', 'requiere_cantidad', 'activo'];

    protected function casts(): array
    {
        return [
            'requiere_cantidad' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function partidas(): HasMany { return $this->hasMany(Partida::class, 'tipo_servicio_id'); }
}
