<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ServicioArchivo extends Model
{
    use LogsActivity;

    protected $table = 'servicio_archivos';

    protected $fillable = ['nombre', 'ruta', 'servicio_id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('documentos')
            ->logOnly(['nombre', 'ruta', 'servicio_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function servicio(): BelongsTo { return $this->belongsTo(Servicio::class, 'servicio_id'); }
}
