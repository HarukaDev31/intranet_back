<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDocumentStatusesCotizacionProveedores extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
            $table->enum('invoice_status', ['Pendiente', 'Recibido', 'Observado', 'Revisado'])
                ->default('Pendiente')
                ->after('factura_comercial');

            $table->enum('packing_status', ['Pendiente', 'Recibido', 'Observado', 'Revisado'])
                ->default('Pendiente')
                ->after('invoice_status');

            $table->enum('excel_conf_status', ['Pendiente', 'Recibido', 'Observado', 'Revisado'])
                ->default('Pendiente')
                ->after('packing_status');
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
            $table->dropColumn(['invoice_status', 'packing_status', 'excel_conf_status']);
        });
    }
}
