<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('servicio_acuses', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('ruta', 500);
            $table->foreignId('servicio_id')->constrained('servicios')->restrictOnDelete();
            $table->foreignId('acuse_original_id')->nullable()->unique()->constrained('servicio_acuses')->restrictOnDelete();
            $table->text('motivo_sustitucion')->nullable();
            $table->foreignId('sustituido_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_acuses');
    }
};
