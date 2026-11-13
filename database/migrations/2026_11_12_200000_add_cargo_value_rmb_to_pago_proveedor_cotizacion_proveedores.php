<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valor de carga en yuanes tal cual lo digita Ventas. Antes solo se guardaba en dólares (redondeado)
 * y el resumen lo reconvertía: ¥10,000 ÷ 7 = $1,428.57 → $1,428.57 × 7 = ¥9,999.99.
 */
return new class extends Migration
{
    private const TABLA = 'pago_proveedor_cotizacion_proveedores';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLA) || Schema::hasColumn(self::TABLA, 'cargo_value_rmb')) {
            return;
        }
        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->decimal('cargo_value_rmb', 14, 2)->nullable()->after('cargo_value_usd')->comment('Valor de la carga en yuanes digitado por Ventas');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLA) || !Schema::hasColumn(self::TABLA, 'cargo_value_rmb')) {
            return;
        }
        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->dropColumn('cargo_value_rmb');
        });
    }
};
