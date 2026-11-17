<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('historial_solicitudes_materiales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_material_id')->constrained('solicitudes_materiales')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('accion', 40);
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30);
            $table->text('observaciones')->nullable();
            $table->dateTime('fecha');
            $table->timestamps();
            $table->index(['solicitud_material_id', 'fecha'], 'hist_sol_material_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_solicitudes_materiales');
    }
};
