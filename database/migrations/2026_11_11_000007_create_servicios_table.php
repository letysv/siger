<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('numero_oficio', 100)->nullable();
            $table->date('fecha_oficio')->nullable();
            $table->dateTime('fecha_recepcion');
            $table->unsignedSmallInteger('ejercicio');
            $table->foreignId('area_solicitante_id')->constrained('cat_areas')->restrictOnDelete();
            $table->string('nombre_solicitante');
            $table->string('dirigido_a');
            $table->string('asunto', 500);
            $table->text('descripcion')->nullable();
            $table->boolean('es_via_rapida')->default(false);
            $table->foreignId('estado_id')->constrained('cat_estados')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['ejercicio', 'fecha_recepcion']);
            $table->index(['area_solicitante_id', 'ejercicio', 'numero_oficio'], 'servicios_posibles_duplicados_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};
