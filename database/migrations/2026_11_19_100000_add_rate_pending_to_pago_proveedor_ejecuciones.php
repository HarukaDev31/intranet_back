<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRY-26-0003 (#2266): China puede registrar el pago al proveedor antes de recibir el dinero del socio.
 * Ese pago queda con el tipo de cambio pendiente («rate_pending») hasta que se registre el ingreso del socio;
 * entonces se recalcula la diferencia de cambio con el tipo de cambio real.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pago_proveedor_ejecuciones', 'rate_pending')) {
            Schema::table('pago_proveedor_ejecuciones', function (Blueprint $table) {
                $table->boolean('rate_pending')->default(false)->after('exchange_result');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pago_proveedor_ejecuciones', 'rate_pending')) {
            Schema::table('pago_proveedor_ejecuciones', function (Blueprint $table) {
                $table->dropColumn('rate_pending');
            });
        }
    }
};
