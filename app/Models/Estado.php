<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Estado extends Model
{
    protected $table = 'cat_estados';

    protected $fillable = ['nombre', 'clave'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function partidas(): HasMany { return $this->hasMany(Partida::class, 'estado_id'); }

    public function servicios(): HasMany { return $this->hasMany(Servicio::class, 'estado_id'); }
}
