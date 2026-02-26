<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nota interna del perfil de Contabilidad por cotización.
     */
    public function up(): void
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->text('nota_contabilidad')->nullable()->after('guia_remision_url');
        });
    }

    public function down(): void
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->dropColumn('nota_contabilidad');
        });
    }
};
