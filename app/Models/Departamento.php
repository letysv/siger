<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Departamento extends Model
{
    use HasFactory;
    use LogsActivity;

    public const SIGLAS_PROTEGIDAS = ['RM', 'SG', 'ADQ'];

    protected $table = 'cat_departamentos';

    protected $fillable = ['nombre', 'siglas', 'titular_responsable', 'ubicacion_oficina', 'activo'];

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
            ->logOnly(['nombre', 'siglas', 'titular_responsable', 'ubicacion_oficina', 'activo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function esProtegido(): bool
    {
        return in_array(strtoupper((string) $this->siglas), self::SIGLAS_PROTEGIDAS, true);
    }

    public function usuarios(): HasMany { return $this->hasMany(User::class, 'departamento_id'); }

    public function operativos(): HasMany { return $this->hasMany(Operativo::class, 'departamento_id'); }

    public function partidas(): HasMany { return $this->hasMany(Partida::class, 'departamento_id'); }

    public function folios(): HasMany { return $this->hasMany(ServicioFolio::class, 'departamento_id'); }
}
