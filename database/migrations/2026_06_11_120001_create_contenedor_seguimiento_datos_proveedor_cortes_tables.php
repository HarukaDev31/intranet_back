<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContenedorSeguimientoDatosProveedorCortesTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('contenedor_seguimiento_corte_periodos')) {
            Schema::create('contenedor_seguimiento_corte_periodos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('id_contenedor');
                $table->dateTime('periodo_inicio');
                $table->dateTime('periodo_fin');
                $table->timestamp('created_at')->nullable();
                $table->index(['id_contenedor', 'periodo_inicio'], 'idx_cscp_contenedor_inicio');
            });
        }

        if (!Schema::hasTable('contenedor_seguimiento_corte_clientes')) {
            Schema::create('contenedor_seguimiento_corte_clientes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_corte');
                $table->unsignedInteger('id_proveedor');
                $table->unsignedInteger('id_cotizacion')->nullable();
                $table->string('nombre_cliente', 255)->nullable();
                $table->string('code_supplier', 128)->nullable();
                $table->string('products', 512)->nullable();
                $table->dateTime('fecha_cambio');
                $table->timestamp('created_at')->nullable();
                $table->index(['id_corte', 'id_proveedor'], 'idx_cscc_corte_proveedor');
                $table->foreign('id_corte', 'fk_cscc_id_corte')
                    ->references('id')
                    ->on('contenedor_seguimiento_corte_periodos')
                    ->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('contenedor_seguimiento_corte_clientes');
        Schema::dropIfExists('contenedor_seguimiento_corte_periodos');
    }
}
