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
        if (!Schema::hasColumn('carga_consolidada_contenedor', 'organizacion_id')) {
            Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
                $table->unsignedInteger('organizacion_id')->nullable()->after('id_pais');
                $table->index('organizacion_id', 'idx_ccc_organizacion_id');
                $table->foreign('organizacion_id', 'fk_ccc_organizacion_id')
                    ->references('ID_Organizacion')
                    ->on('organizacion')
                    ->onDelete('restrict');
            });
        }

        // Backfill: toda la data existente pertenece a la única organización actual.
        $organizacionActual = DB::table('organizacion')->orderBy('ID_Organizacion')->value('ID_Organizacion');
        if ($organizacionActual !== null) {
            DB::table('carga_consolidada_contenedor')
                ->whereNull('organizacion_id')
                ->update(['organizacion_id' => $organizacionActual]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('carga_consolidada_contenedor', 'organizacion_id')) {
            Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
                $table->dropForeign('fk_ccc_organizacion_id');
                $table->dropIndex('idx_ccc_organizacion_id');
                $table->dropColumn('organizacion_id');
            });
        }
    }
};
