<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rellena `calculadora_importacion_proveedores.id_proveedor` haciendo match
     * entre `code_supplier` de:
     * - `calculadora_importacion_proveedores`
     * - `contenedor_consolidado_cotizacion_proveedores`
     *
     * pero solo para proveedores cuya cotización (tabla contenedora) fue creada este año,
     * usando `contenedor_consolidado_cotizacion.fecha`.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('calculadora_importacion_proveedores', 'id_proveedor')) {
            return;
        }

        $year = (int) date('Y');

        DB::statement(
            "UPDATE calculadora_importacion_proveedores cip
             JOIN calculadora_importacion ci
               ON ci.id = cip.id_calculadora_importacion
             JOIN contenedor_consolidado_cotizacion cco
               ON cco.id = ci.id_cotizacion
             JOIN contenedor_consolidado_cotizacion_proveedores cccp
               ON cccp.id_cotizacion = cco.id
              AND cccp.code_supplier = cip.code_supplier
             SET cip.id_proveedor = cccp.id
             WHERE cip.id_proveedor IS NULL
               AND cip.code_supplier IS NOT NULL
               AND YEAR(cco.fecha) = ?",
            [$year]
        );
    }

    /**
     * Limpia el campo para este año.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('calculadora_importacion_proveedores', 'id_proveedor')) {
            return;
        }

        $year = (int) date('Y');

        DB::statement(
            "UPDATE calculadora_importacion_proveedores cip
             JOIN calculadora_importacion ci
               ON ci.id = cip.id_calculadora_importacion
             JOIN contenedor_consolidado_cotizacion cco
               ON cco.id = ci.id_cotizacion
             SET cip.id_proveedor = NULL
             WHERE YEAR(cco.fecha) = ?",
            [$year]
        );
    }
};

