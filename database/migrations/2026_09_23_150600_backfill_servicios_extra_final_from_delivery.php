<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cargas antiguas tenían DELIVERY/MONTACARGA/SANCIONES/BQ en
 * contenedor_consolidado_cotizacion_delivery_servicio, pero
 * servicios_extra_final quedó en 0. Sin ese campo el header Vendido
 * no incluye extras (mientras Pagado sí cuenta DELIVERY).
 * Se rellena con la misma suma que usa la plantilla nueva.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $cotizacion = 'contenedor_consolidado_cotizacion';
        $servicios = 'contenedor_consolidado_cotizacion_delivery_servicio';

        if (
            !Schema::hasTable($cotizacion)
            || !Schema::hasTable($servicios)
            || !Schema::hasColumn($cotizacion, 'servicios_extra_final')
        ) {
            return;
        }

        DB::statement("
            UPDATE {$cotizacion} ccc
            INNER JOIN (
                SELECT
                    id_cotizacion,
                    ROUND(SUM(importe), 2) AS total_extra
                FROM {$servicios}
                WHERE UPPER(TRIM(tipo_servicio)) IN ('MONTACARGA', 'DELIVERY', 'SANCIONES', 'BQ')
                GROUP BY id_cotizacion
                HAVING ROUND(SUM(importe), 2) > 0
            ) s ON s.id_cotizacion = ccc.id
            SET ccc.servicios_extra_final = s.total_extra
            WHERE ccc.deleted_at IS NULL
              AND IFNULL(ccc.servicios_extra_final, 0) = 0
        ");

        // Fallback: si no hay filas de servicio pero sí voucher DELIVERY
        if (
            Schema::hasTable('contenedor_consolidado_cotizacion_coordinacion_pagos')
            && Schema::hasTable('cotizacion_coordinacion_pagos_concept')
        ) {
            DB::statement("
                UPDATE {$cotizacion} ccc
                INNER JOIN (
                    SELECT
                        p.id_cotizacion,
                        ROUND(SUM(p.monto), 2) AS total_delivery
                    FROM contenedor_consolidado_cotizacion_coordinacion_pagos p
                    INNER JOIN cotizacion_coordinacion_pagos_concept x ON x.id = p.id_concept
                    WHERE x.name = 'DELIVERY'
                    GROUP BY p.id_cotizacion
                    HAVING ROUND(SUM(p.monto), 2) > 0
                ) d ON d.id_cotizacion = ccc.id
                SET ccc.servicios_extra_final = d.total_delivery
                WHERE ccc.deleted_at IS NULL
                  AND IFNULL(ccc.servicios_extra_final, 0) = 0
            ");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No se revierte: no hay snapshot del valor anterior (0).
    }
};
