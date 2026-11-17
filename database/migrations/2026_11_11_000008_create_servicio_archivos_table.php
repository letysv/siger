<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('servicio_archivos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('ruta', 500);
            $table->foreignId('servicio_id')->constrained('servicios')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_archivos');
    }
};
