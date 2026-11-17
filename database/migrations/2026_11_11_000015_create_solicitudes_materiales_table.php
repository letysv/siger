<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('solicitudes_materiales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partida_id')->constrained('partidas')->restrictOnDelete();
            $table->foreignId('usuario_solicitante_id')->constrained('users')->restrictOnDelete();
            $table->text('descripcion');
            $table->decimal('cantidad', 12, 2)->nullable();
            $table->string('unidad_medida', 100)->nullable();
            $table->text('justificacion');
            $table->string('estado', 30)->default('BORRADOR');
            $table->dateTime('fecha_envio')->nullable();
            $table->foreignId('usuario_resolucion_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('fecha_resolucion')->nullable();
            $table->text('observaciones_direccion')->nullable();
            $table->foreignId('partida_generada_id')->nullable()->unique()->constrained('partidas')->restrictOnDelete();
            $table->timestamps();
            $table->index(['partida_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_materiales');
    }
};
