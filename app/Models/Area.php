<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Area extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'cat_areas';

    protected $fillable = ['clave', 'nombre', 'titular', 'tipo_area', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalogos')
            ->logOnly(['clave', 'nombre', 'titular', 'tipo_area', 'activo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function servicios(): HasMany { return $this->hasMany(Servicio::class, 'area_solicitante_id'); }
}
