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
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            // Límite máximo de CBM IMO por contenedor. Null = sin límite.
            $table->decimal('limite_cbm_imo', 10, 2)->nullable()->after('f_inicio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            $table->dropColumn('limite_cbm_imo');
        });
    }
};

