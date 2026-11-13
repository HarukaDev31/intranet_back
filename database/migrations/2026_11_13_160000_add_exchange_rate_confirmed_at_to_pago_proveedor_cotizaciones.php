<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo Ventas confirmó el tipo de cambio de la cotización. La confirmación solo vale el mismo día
 * (hora de Lima): una cotización de otro día debe volver a confirmar el TC antes de crear la solicitud.
 */
return new class extends Migration
{
    private const TABLA = 'pago_proveedor_cotizaciones';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLA) || Schema::hasColumn(self::TABLA, 'exchange_rate_confirmed_at')) {
            return;
        }
        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->timestamp('exchange_rate_confirmed_at')->nullable()->after('exchange_rate_confirmed')->comment('Cuándo Ventas confirmó el tipo de cambio');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLA) || !Schema::hasColumn(self::TABLA, 'exchange_rate_confirmed_at')) {
            return;
        }
        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->dropColumn('exchange_rate_confirmed_at');
        });
    }
};
