<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formato Bolivia de la cotización resumen: cada concepto guarda su valor en Bs y la
 * tasa de cambio del documento. `valor` sigue siendo el monto en USD (único que se muestra).
 */
class AddValorBsTasaCambioToCotizacionProveedorResumenCosto extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('cotizacion_proveedor_resumen_costo')) {
            return;
        }

        Schema::table('cotizacion_proveedor_resumen_costo', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizacion_proveedor_resumen_costo', 'valor_bs')) {
                $table->decimal('valor_bs', 14, 2)->nullable()->after('valor');
            }
            if (!Schema::hasColumn('cotizacion_proveedor_resumen_costo', 'tasa_cambio')) {
                $table->decimal('tasa_cambio', 10, 4)->nullable()->after('valor_bs');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('cotizacion_proveedor_resumen_costo')) {
            return;
        }

        Schema::table('cotizacion_proveedor_resumen_costo', function (Blueprint $table) {
            if (Schema::hasColumn('cotizacion_proveedor_resumen_costo', 'tasa_cambio')) {
                $table->dropColumn('tasa_cambio');
            }
            if (Schema::hasColumn('cotizacion_proveedor_resumen_costo', 'valor_bs')) {
                $table->dropColumn('valor_bs');
            }
        });
    }
}
