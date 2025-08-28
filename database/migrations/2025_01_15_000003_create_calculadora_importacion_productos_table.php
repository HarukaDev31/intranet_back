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
        Schema::create('calculadora_importacion_productos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_proveedor');
            $table->string('nombre');
            $table->decimal('precio', 10, 2);
            $table->integer('valoracion')->default(0);
            $table->integer('cantidad');
            $table->decimal('antidumping_cu', 10, 2)->default(0); // Antidumping por unidad
            $table->decimal('ad_valorem_p', 10, 2)->default(0); // Ad valorem porcentual
            $table->timestamps();

            $table->foreign('id_proveedor', 'fk_calc_imp_prod_prov')
                  ->references('id')
                  ->on('calculadora_importacion_proveedores')
                  ->onDelete('cascade');
            
            $table->index(['id_proveedor', 'nombre'], 'idx_calc_imp_prod_prov_nom');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calculadora_importacion_productos');
    }
};
