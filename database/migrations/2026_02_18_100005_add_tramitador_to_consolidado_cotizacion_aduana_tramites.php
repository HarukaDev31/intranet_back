<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tramitador (decimal 10,2) compartido por permiso (para todos los tipo_permiso del trámite).
     */
    public function up(): void
    {
        Schema::table('consolidado_cotizacion_aduana_tramites', function (Blueprint $table) {
            $table->decimal('tramitador', 10, 2)->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('consolidado_cotizacion_aduana_tramites', function (Blueprint $table) {
            $table->dropColumn('tramitador');
        });
    }
};
