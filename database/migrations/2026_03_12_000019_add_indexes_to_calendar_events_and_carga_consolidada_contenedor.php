<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //drop if exists
        Schema::dropIfExists('calendar_events_calendar_deleted_index');
        Schema::dropIfExists('carga_consolidada_contenedor_estado_finicio_carga_index');
        if (Schema::hasTable('carga_consolidada_contenedor')) {
            Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
                // Índice compuesto para filtros frecuentes en carga consolidada
                // Usamos solo columnas no TEXT/BLOB para evitar errores de MySQL.
                $table->index(
                    ['estado_documentacion', 'f_inicio'],
                    'carga_consolidada_contenedor_estado_finicio_index'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('carga_consolidada_contenedor')) {
            Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
                $table->dropIndex('carga_consolidada_contenedor_estado_finicio_index');
            });
        }
    }
};

