<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServicioArchivo extends Model
{
    protected $table = 'servicio_archivos';

    protected $fillable = ['nombre', 'ruta', 'servicio_id'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function servicio(): BelongsTo { return $this->belongsTo(Servicio::class, 'servicio_id'); }
}
