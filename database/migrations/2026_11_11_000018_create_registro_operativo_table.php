<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('registro_operativo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partida_id')->unique()->constrained('partidas')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('fecha_conclusion');
            $table->text('descripcion_trabajo');
            $table->text('descripcion_materiales')->nullable();
            $table->json('evidencias')->nullable();
            $table->boolean('conformidad_recibida')->default(false);
            $table->string('recibe_nombre')->nullable();
            $table->string('recibe_cargo')->nullable();
            $table->string('archivo_conformidad', 500)->nullable();
            $table->text('motivo_sin_conformidad')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_operativo');
    }
};
