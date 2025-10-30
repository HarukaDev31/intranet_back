<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContenedorConsolidadoCotizacionProveedoresItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Verificar si la tabla ya existe
        Schema::dropIfExists('contenedor_consolidado_cotizacion_proveedores_items');
        Schema::create('contenedor_consolidado_cotizacion_proveedores_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_contenedor');
            $table->integer('id_cotizacion');
            $table->unsignedInteger('id_proveedor');
            $table->foreign('id_contenedor', 'fk_cccp_items_contenedor')
                ->references('id')
                ->on('carga_consolidada_contenedor');
            $table->foreign('id_cotizacion', 'fk_cccp_items_cotizacion')
                ->references('id')
                ->on('contenedor_consolidado_cotizacion');
            $table->foreign('id_proveedor', 'fk_cccp_items_proveedor')
                ->references('id')
                ->on('contenedor_consolidado_cotizacion_proveedores');
            //initial_cbm,initia_qty,final_cbm,final_qty,initial_name,final_name
            $table->decimal('initial_price', 10, 2)->nullable();
            $table->integer('initial_qty')->nullable();
            $table->text('initial_name')->nullable();
            $table->decimal('final_price', 10, 2)->nullable();
            $table->integer('final_qty')->nullable();
            $table->text('final_name')->nullable();
            //tipo_producto,
            $table->enum('tipo_producto', ['GENERAL', 'CALZADO','ROPA','TECNOLOGIA','TELA','AUTOMOTRIZ','MOVILIDAD PERSONAL'])->default('GENERAL');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('contenedor_consolidado_cotizacion_proveedores_items');
    }
}
