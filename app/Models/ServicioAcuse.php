<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ServicioAcuse extends Model
{
    use LogsActivity;

    protected $table = 'servicio_acuses';

    protected $fillable = ['nombre', 'ruta', 'servicio_id', 'acuse_original_id', 'motivo_sustitucion', 'sustituido_por'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('documentos')
            ->logOnly(['nombre', 'ruta', 'servicio_id', 'acuse_original_id', 'motivo_sustitucion', 'sustituido_por'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function servicio(): BelongsTo { return $this->belongsTo(Servicio::class, 'servicio_id'); }

    public function original(): BelongsTo { return $this->belongsTo(self::class, 'acuse_original_id'); }

    public function sustituto(): HasOne { return $this->hasOne(self::class, 'acuse_original_id'); }

    public function usuarioSustitucion(): BelongsTo { return $this->belongsTo(User::class, 'sustituido_por'); }
}
