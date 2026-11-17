<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operativos_partida', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partida_id')->constrained('partidas')->restrictOnDelete();
            $table->foreignId('operativo_id')->constrained('cat_operativos')->restrictOnDelete();
            $table->dateTime('fecha_asignacion');
            $table->dateTime('fecha_finalizacion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['partida_id', 'activo']);
            $table->index(['operativo_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operativos_partida');
    }
};
