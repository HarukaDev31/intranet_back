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
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            // Cambiar las columnas de monto_final, logistica_final, impuestos_final y fob_final
            // de DECIMAL a DECIMAL con mayor precisión para evitar errores de rango
            $table->decimal('monto_final', 15, 2)->nullable()->change();
            $table->decimal('logistica_final', 15, 2)->nullable()->change();
            $table->decimal('impuestos_final', 15, 2)->nullable()->change();
            $table->decimal('fob_final', 15, 2)->nullable()->change();
            $table->decimal('tarifa_final', 15, 2)->nullable()->change();
            $table->decimal('volumen_final', 15, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            // Revertir a tipos más pequeños (esto podría causar errores si hay datos grandes)
            $table->decimal('monto_final', 10, 2)->nullable()->change();
            $table->decimal('logistica_final', 10, 2)->nullable()->change();
            $table->decimal('impuestos_final', 10, 2)->nullable()->change();
            $table->decimal('fob_final', 10, 2)->nullable()->change();
            $table->decimal('tarifa_final', 10, 2)->nullable()->change();
            $table->decimal('volumen_final', 10, 2)->nullable()->change();
        });
    }
};