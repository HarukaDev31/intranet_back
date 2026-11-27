<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CERRADO del Encargado China: el consolidado sigue en COMPLETADO y, al completarse las 3 etapas
 * (Reserva, Antes del llenado y Después del llenado), se marca aquí la fecha. Solo se muestra en Completados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            $table->timestamp('china_cerrado_at')->nullable()->comment('Fecha en que el Encargado China completó las 3 etapas (estado CERRADO)');
        });
    }

    public function down(): void
    {
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            $table->dropColumn('china_cerrado_at');
        });
    }
};
