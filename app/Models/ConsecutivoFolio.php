<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ConsecutivoFolio extends Model
{
    use LogsActivity;

    protected $table = 'consecutivos_folios';

    protected $fillable = ['departamento_id', 'ejercicio', 'ultimo_numero'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('folios')
            ->logOnly(['departamento_id', 'ejercicio', 'ultimo_numero'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function departamento(): BelongsTo { return $this->belongsTo(Departamento::class, 'departamento_id'); }
}
