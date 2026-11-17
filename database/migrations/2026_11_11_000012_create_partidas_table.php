<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('partidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('servicios')->restrictOnDelete();
            $table->foreignId('partida_origen_id')->nullable()->constrained('partidas')->restrictOnDelete();
            $table->unsignedInteger('numero_partida');
            $table->foreignId('servicio_folio_id')->nullable()->constrained('servicio_folios')->restrictOnDelete();
            $table->foreignId('tipo_servicio_id')->nullable()->constrained('cat_tipo_servicios')->restrictOnDelete();
            $table->text('concepto')->nullable();
            $table->decimal('cantidad_solicitada', 12, 2)->nullable();
            $table->decimal('cantidad_atendida', 12, 2)->nullable();
            $table->string('unidad_medida', 100)->nullable();
            $table->foreignId('departamento_id')->nullable()->constrained('cat_departamentos')->restrictOnDelete();
            $table->text('observaciones')->nullable();
            $table->string('nombre_quien_recibe_material')->nullable();
            $table->foreignId('estado_id')->constrained('cat_estados')->restrictOnDelete();
            $table->dateTime('fecha_inicio')->nullable();
            $table->dateTime('fecha_conclusion')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('fecha_cancelacion')->nullable();
            $table->timestamps();
            $table->unique(['servicio_id', 'numero_partida']);
            $table->index(['departamento_id', 'estado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partidas');
    }
};
