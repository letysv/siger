<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('consecutivos_folios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')->constrained('cat_departamentos')->restrictOnDelete();
            $table->unsignedSmallInteger('ejercicio');
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();
            $table->unique(['departamento_id', 'ejercicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consecutivos_folios');
    }
};
