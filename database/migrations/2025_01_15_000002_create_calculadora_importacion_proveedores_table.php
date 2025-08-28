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
        if (Schema::hasTable('calculadora_importacion_proveedores')) {
            //drop table
            Schema::dropIfExists('calculadora_importacion_proveedores');
        }
        Schema::create('calculadora_importacion_proveedores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_calculadora_importacion');
            $table->decimal('cbm', 8, 2); // Metros cúbicos
            $table->decimal('peso', 10, 2); // Peso en kg
            $table->integer('qty_caja'); // Cantidad de cajas
            $table->timestamps();

            $table->foreign('id_calculadora_importacion', 'fk_calc_imp_prov_calc_imp')
                  ->references('id')
                  ->on('calculadora_importacion')
                  ->onDelete('cascade');

            $table->index(['id_calculadora_importacion', 'cbm'], 'idx_calc_imp_prov_calc_cbm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calculadora_importacion_proveedores');
    }
};
