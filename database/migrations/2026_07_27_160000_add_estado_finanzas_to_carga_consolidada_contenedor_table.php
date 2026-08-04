<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('carga_consolidada_contenedor', 'estado_finanzas')) {
            Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
                $table->enum('estado_finanzas', ['PENDIENTE', 'COMPLETADO'])
                    ->default('PENDIENTE')
                    ->after('estado_documentacion');
            });
        }

        // Backfill: ya generaron plantilla final con al menos un cliente exitoso.
        if (Schema::hasTable('consolidado_plantilla_final_batches')) {
            DB::statement("
                UPDATE carga_consolidada_contenedor c
                INNER JOIN (
                    SELECT DISTINCT id_contenedor
                    FROM consolidado_plantilla_final_batches
                    WHERE estado = 'COMPLETED'
                      AND clientes_completados > 0
                ) b ON b.id_contenedor = c.id
                SET c.estado_finanzas = 'COMPLETADO'
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('carga_consolidada_contenedor', 'estado_finanzas')) {
            Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
                $table->dropColumn('estado_finanzas');
            });
        }
    }
};
