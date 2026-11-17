<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('historial_turnados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partida_id')->constrained('partidas')->restrictOnDelete();
            $table->foreignId('departamento_origen_id')->nullable()->constrained('cat_departamentos')->restrictOnDelete();
            $table->foreignId('departamento_destino_id')->constrained('cat_departamentos')->restrictOnDelete();
            $table->foreignId('servicio_folio_origen_id')->nullable()->constrained('servicio_folios')->restrictOnDelete();
            $table->foreignId('servicio_folio_destino_id')->constrained('servicio_folios')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->text('motivo')->nullable();
            $table->dateTime('fecha');
            $table->timestamps();
            $table->index(['partida_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_turnados');
    }
};
