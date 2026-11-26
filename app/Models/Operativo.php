<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Operativo extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'cat_operativos';

    protected $fillable = ['nombre', 'departamento_id', 'user_id', 'activo'];

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
            ->logOnly(['nombre', 'departamento_id', 'user_id', 'activo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function departamento(): BelongsTo { return $this->belongsTo(Departamento::class, 'departamento_id'); }

    public function usuario(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }

    public function asignaciones(): HasMany { return $this->hasMany(OperativoPartida::class, 'operativo_id'); }
}
