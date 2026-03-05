<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //drop table if exists
        Schema::dropIfExists('boletin_quimico_cotizacion_item');
        Schema::create('boletin_quimico_cotizacion_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_contenedor');
            $table->integer('id_cotizacion');
            $table->unsignedBigInteger('id_cotizacion_proveedor_item')->nullable()->comment('FK a contenedor_consolidado_cotizacion_proveedores_items');
            $table->decimal('monto_boletin', 12, 4)->default(0);
            $table->string('estado', 32)->default('pendiente')->comment('pendiente, adelanto_pagado, pagado');
            $table->timestamps();
            $table->foreign('id_contenedor', 'bq_item_id_contenedor_fk')->references('id')->on('carga_consolidada_contenedor')->onDelete('cascade');
            $table->foreign('id_cotizacion', 'bq_item_id_cotizacion_fk')->references('id')->on('contenedor_consolidado_cotizacion')->onDelete('cascade');
            $table->foreign('id_cotizacion_proveedor_item', 'bq_item_id_prov_item_fk')->references('id')->on('contenedor_consolidado_cotizacion_proveedores_items')->onDelete('cascade');
            $table->unique(['id_cotizacion', 'id_cotizacion_proveedor_item'], 'bq_unique_cotizacion_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boletin_quimico_cotizacion_item');
    }
};
