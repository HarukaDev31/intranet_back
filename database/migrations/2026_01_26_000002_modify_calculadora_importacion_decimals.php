<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Cambia todos los campos decimal a decimal(20,10) para soportar 10 decimales.
     */
    public function up(): void
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->decimal('tarifa_total_extra_proveedor', 20, 10)->default(0)->change();
            $table->decimal('tarifa_total_extra_item', 20, 10)->default(0)->change();
            $table->decimal('tarifa', 20, 10)->nullable()->change();
            $table->decimal('tarifa_descuento', 20, 10)->default(0)->change();
            $table->decimal('tc', 20, 10)->nullable()->change();
            $table->decimal('total_fob', 20, 10)->nullable()->change();
            $table->decimal('total_impuestos', 20, 10)->nullable()->change();
            $table->decimal('logistica', 20, 10)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->decimal('tarifa_total_extra_proveedor', 10, 2)->default(0)->change();
            $table->decimal('tarifa_total_extra_item', 10, 2)->default(0)->change();
            $table->decimal('tarifa', 10, 2)->nullable()->change();
            $table->decimal('tarifa_descuento', 10, 2)->default(0)->change();
            $table->decimal('tc', 10, 4)->nullable()->change();
            $table->decimal('total_fob', 15, 2)->nullable()->change();
            $table->decimal('total_impuestos', 15, 2)->nullable()->change();
            $table->decimal('logistica', 15, 2)->nullable()->change();
        });
    }
};
