<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSeguimientoFieldsToCotizacionProveedores extends Migration
{
    /**
     * Columnas nuevas del módulo Cliente / Seguimiento: Canal, Fecha de Entrega
     * y Observaciones, editables por ambos perfiles de coordinación (Daniela/José),
     * una por cada proveedor.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'canal')) {
                $table->enum('canal', ['Bitrix', 'Api'])->nullable()->after('excel_conf_status_final');
            }
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'fecha_entrega')) {
                $table->date('fecha_entrega')->nullable()->after('canal');
            }
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'observaciones_seguimiento')) {
                $table->text('observaciones_seguimiento')->nullable()->after('fecha_entrega');
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
            foreach (['canal', 'fecha_entrega', 'observaciones_seguimiento'] as $col) {
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
