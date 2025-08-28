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
        Schema::create('calculadora_importacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_cliente')->nullable();
            $table->string('nombre_cliente');
            $table->string('dni_cliente');
            $table->string('correo_cliente')->nullable();
            $table->string('whatsapp_cliente')->nullable();
            $table->string('tipo_cliente')->default('NUEVO');
            $table->integer('qty_proveedores');
            $table->decimal('tarifa_total_extra_proveedor', 10, 2)->default(0);
            $table->decimal('tarifa_total_extra_item', 10, 2)->default(0);
            $table->timestamps();

            $table->foreign('id_cliente', 'fk_calc_imp_cliente')->references('id')->on('clientes')->onDelete('set null');
            $table->index(['dni_cliente', 'tipo_cliente']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calculadora_importacion');
    }
};
