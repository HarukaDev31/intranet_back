<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDocumentStatusFinalToCotizacionProveedores extends Migration
{
    /**
     * Estados de visto bueno final (Coord 1/3 y resto).
     * Coord 2 usa invoice_status / packing_status / excel_conf_status.
     * Al pasar Coord 2 a Revisado, el *_final correspondiente queda en Recibido.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'invoice_status_final')) {
                $table->enum('invoice_status_final', ['Pendiente', 'Recibido', 'Observado', 'Revisado'])
                    ->default('Pendiente')
                    ->after('excel_conf_status');
            }
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'packing_status_final')) {
                $table->enum('packing_status_final', ['Pendiente', 'Recibido', 'Observado', 'Revisado'])
                    ->default('Pendiente')
                    ->after('invoice_status_final');
            }
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'excel_conf_status_final')) {
                $table->enum('excel_conf_status_final', ['Pendiente', 'Recibido', 'Observado', 'Revisado'])
                    ->default('Pendiente')
                    ->after('packing_status_final');
            }
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
            $cols = [];
            foreach (['invoice_status_final', 'packing_status_final', 'excel_conf_status_final'] as $col) {
                if (Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
}
