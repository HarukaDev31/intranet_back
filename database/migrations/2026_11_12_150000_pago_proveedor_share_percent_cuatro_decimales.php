<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Porcentajes de las cuotas con 4 decimales: con 2 decimales, un monto escrito a mano
 * (p. ej. 6000 de 9999.99) se volvía 5999.99 al recalcularlo desde el porcentaje.
 */
return new class extends Migration
{
    private const TABLAS = ['pago_proveedor_cotizacion_proveedores', 'pago_proveedor_solicitud_proveedores'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->decimal('first_share_percent', 8, 4)->default(100)->comment('Porcentaje de la primera cuota')->change();
                $table->decimal('second_share_percent', 8, 4)->nullable()->comment('Porcentaje de la segunda cuota')->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->decimal('first_share_percent', 5, 2)->default(100)->comment('Porcentaje de la primera cuota')->change();
                $table->decimal('second_share_percent', 5, 2)->nullable()->comment('Porcentaje de la segunda cuota')->change();
            });
        }
    }
};
