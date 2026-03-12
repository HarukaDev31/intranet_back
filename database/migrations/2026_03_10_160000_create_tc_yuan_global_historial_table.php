<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial del TC Yuan global. Cada guardado crea un nuevo registro;
     * el registro anterior se cierra actualizando su updated_at.
     */
    public function up(): void
    {
        Schema::create('tc_yuan_global_historial', function (Blueprint $table) {
            $table->id();
            $table->decimal('tc_yuan', 18, 8)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable(); // se llena al cerrar el periodo (siguiente guardado)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tc_yuan_global_historial');
    }
};
