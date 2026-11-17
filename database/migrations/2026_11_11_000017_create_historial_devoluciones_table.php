<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('historial_devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partida_id')->constrained('partidas')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('estado_anterior_id')->constrained('cat_estados')->restrictOnDelete();
            $table->text('observaciones');
            $table->dateTime('fecha_devolucion');
            $table->text('respuesta_subsanacion')->nullable();
            $table->foreignId('reanudado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('fecha_reanudacion')->nullable();
            $table->string('resultado_cierre', 20)->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('fecha_cierre')->nullable();
            $table->timestamps();
            $table->index(['partida_id', 'fecha_devolucion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_devoluciones');
    }
};
