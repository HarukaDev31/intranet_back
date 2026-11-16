<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Con qué tarifa del socio se creó la operación (1 o 2; vacío si la tarifa se escribió a mano).
 * Junto con la fecha se cruza con organizacion_tarifas_historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('china_operations', function (Blueprint $table) {
            $table->unsignedTinyInteger('rate_tier')->nullable()->after('rate')->comment('Tarifa del socio usada: 1, 2 o vacío si fue manual');
        });
    }

    public function down(): void
    {
        Schema::table('china_operations', function (Blueprint $table) {
            $table->dropColumn('rate_tier');
        });
    }
};
