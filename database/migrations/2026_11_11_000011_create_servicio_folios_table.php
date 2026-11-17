<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('servicio_folios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('servicios')->restrictOnDelete();
            $table->foreignId('departamento_id')->constrained('cat_departamentos')->restrictOnDelete();
            $table->unsignedSmallInteger('ejercicio');
            $table->unsignedTinyInteger('mes');
            $table->unsignedInteger('consecutivo');
            $table->string('folio', 30)->unique();
            $table->dateTime('fecha_asignacion');
            $table->timestamps();
            $table->unique(['servicio_id', 'departamento_id']);
            $table->unique(['departamento_id', 'ejercicio', 'consecutivo'], 'folio_depto_ejercicio_consecutivo_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_folios');
    }
};
