<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('carga_consolidada_contenedor', 'deleted_at')) {
            Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
        if (!Schema::hasColumn('carga_consolidada_contenedor', 'id_contenedor_origen')) {
            Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
                // Apunta al consolidado original (parte A). Null = no partido / normal.
                $table->unsignedInteger('id_contenedor_origen')->nullable()->after('parte');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('carga_consolidada_contenedor', 'id_contenedor_origen')) {
            Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
                $table->dropColumn('id_contenedor_origen');
            });
        }
        if (Schema::hasColumn('carga_consolidada_contenedor', 'deleted_at')) {
            Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
