<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consolidado_comprobante_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_contenedor');
            $table->unsignedBigInteger('id_user');
            $table->integer('id_cotizacion');
            $table->enum('tipo_comprobante', ['BOLETA', 'FACTURA']);
            $table->string('destino_entrega')->nullable();
            // Datos para FACTURA
            $table->string('razon_social')->nullable();
            $table->string('ruc', 20)->nullable();
            // Datos para BOLETA
            $table->string('nombre_completo')->nullable();
            $table->string('dni_carnet', 20)->nullable();

            $table->foreign('id_contenedor')->references('id')->on('carga_consolidada_contenedor')->onDelete('cascade');
            $table->foreign('id_user')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('id_cotizacion')->references('id')->on('contenedor_consolidado_cotizacion')->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consolidado_comprobante_forms');
    }
};
