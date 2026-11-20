<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TipoServicio extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'cat_tipo_servicios';

    protected $fillable = ['descripcion', 'requiere_cantidad', 'activo'];

    protected function casts(): array
    {
        return [
            'requiere_cantidad' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalogos')
            ->logOnly(['descripcion', 'requiere_cantidad', 'activo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function partidas(): HasMany { return $this->hasMany(Partida::class, 'tipo_servicio_id'); }
}
