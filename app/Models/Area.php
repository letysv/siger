<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Area extends Model
{
    protected $table = 'cat_areas';

    protected $fillable = ['clave', 'nombre', 'titular', 'tipo_area', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function servicios(): HasMany { return $this->hasMany(Servicio::class, 'area_solicitante_id'); }
}
