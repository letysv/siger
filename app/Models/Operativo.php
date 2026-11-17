<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Operativo extends Model
{
    protected $table = 'cat_operativos';

    protected $fillable = ['nombre', 'departamento_id', 'user_id', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function departamento(): BelongsTo { return $this->belongsTo(Departamento::class, 'departamento_id'); }

    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }

    public function asignaciones(): HasMany { return $this->hasMany(OperativoPartida::class, 'operativo_id'); }
}
