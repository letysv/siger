<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConsecutivoFolio extends Model
{
    protected $table = 'consecutivos_folios';

    protected $fillable = ['departamento_id', 'ejercicio', 'ultimo_numero'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function departamento(): BelongsTo { return $this->belongsTo(Departamento::class, 'departamento_id'); }
}
