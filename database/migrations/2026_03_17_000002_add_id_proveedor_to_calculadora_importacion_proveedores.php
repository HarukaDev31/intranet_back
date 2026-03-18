<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega `id_proveedor` para relacionar cada fila de
     * `calculadora_importacion_proveedores` con su proveedor equivalente en
     * `contenedor_consolidado_cotizacion_proveedores`.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('calculadora_importacion_proveedores', 'id_proveedor')) {
            Schema::table('calculadora_importacion_proveedores', function (Blueprint $table) {
                $table->unsignedInteger('id_proveedor')
                    ->nullable()
                    ->after('code_supplier');

                $table->foreign('id_proveedor', 'cc_ci_prov_id_proveedor_fk')
                    ->references('id')
                    ->on('contenedor_consolidado_cotizacion_proveedores')
                    ->onDelete('set null');

                $table->index('id_proveedor', 'idx_cc_ci_prov_id_proveedor');
            });
        }
    }

    /**
     * Revierte el cambio (borra FK y columna).
     */
    public function down(): void
    {
        if (Schema::hasColumn('calculadora_importacion_proveedores', 'id_proveedor')) {
            Schema::table('calculadora_importacion_proveedores', function (Blueprint $table) {
                // dropForeign por nombre es más seguro que por convención.
                $table->dropForeign('cc_ci_prov_id_proveedor_fk');
                $table->dropIndex('idx_cc_ci_prov_id_proveedor');
                $table->dropColumn('id_proveedor');
            });
        }
    }
};

