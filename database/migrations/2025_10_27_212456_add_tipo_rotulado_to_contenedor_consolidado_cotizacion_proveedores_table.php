<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTipoRotuladoToContenedorConsolidadoCotizacionProveedoresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
            $table->enum('tipo_rotulado', ['rotulado', 'calzado', 'ropa', 'ropa_interior', 'maquinaria', 'movilidad_personal'])
                  ->after('code_supplier')
                  ->comment('Tipo de rotulado a aplicar al proveedor');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
            $table->dropColumn('tipo_rotulado');
        });
    }
}
