<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1) Sanción registrada solo como voucher IMPUESTOS (ej. Damaris) → línea SANCIONES.
 * 2) Recalcula servicios_extra_final = DELIVERY + MONTACARGA + SANCIONES + BQ
 *    (igual que la plantilla nueva / header Vendido).
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
        $pagos = 'contenedor_consolidado_cotizacion_coordinacion_pagos';

        if (
            !Schema::hasTable($cotizacion)
            || !Schema::hasTable($servicios)
            || !Schema::hasColumn($cotizacion, 'servicios_extra_final')
        ) {
            return;
        }

        // Damaris y similares: voucher "( sancion)" como IMPUESTOS sin fila SANCIONES
        if (Schema::hasTable($pagos) && Schema::hasTable('cotizacion_coordinacion_pagos_concept')) {
            $rows = DB::select("
                SELECT
                    p.id_cotizacion,
                    ROUND(SUM(p.monto), 2) AS total_sancion,
                    MAX(c.organizacion_id) AS organizacion_id
                FROM {$pagos} p
                INNER JOIN cotizacion_coordinacion_pagos_concept x ON x.id = p.id_concept
                INNER JOIN {$cotizacion} c ON c.id = p.id_cotizacion AND c.deleted_at IS NULL
                WHERE x.name = 'IMPUESTOS'
                  AND LOWER(IFNULL(p.voucher_url, '')) LIKE '%sancion%'
                GROUP BY p.id_cotizacion
                HAVING ROUND(SUM(p.monto), 2) > 0
            ");

            foreach ($rows as $row) {
                $idCotizacion = (int) $row->id_cotizacion;
                $exists = DB::table($servicios)
                    ->where('id_cotizacion', $idCotizacion)
                    ->whereRaw("UPPER(TRIM(tipo_servicio)) = 'SANCIONES'")
                    ->exists();
                if ($exists) {
                    continue;
                }
                $insert = [
                    'id_cotizacion' => $idCotizacion,
                    'tipo_servicio' => 'SANCIONES',
                    'importe' => (float) $row->total_sancion,
                ];
                if (Schema::hasColumn($servicios, 'organizacion_id') && !empty($row->organizacion_id)) {
                    $insert['organizacion_id'] = (int) $row->organizacion_id;
                }
                DB::table($servicios)->insert($insert);
            }
        }

        // Recalcular servicios_extra_final con TODOS los cargos extra
        DB::statement("
            UPDATE {$cotizacion} ccc
            LEFT JOIN (
                SELECT
                    id_cotizacion,
                    ROUND(SUM(importe), 2) AS total_extra
                FROM {$servicios}
                WHERE UPPER(TRIM(tipo_servicio)) IN ('MONTACARGA', 'DELIVERY', 'SANCIONES', 'BQ')
                GROUP BY id_cotizacion
            ) s ON s.id_cotizacion = ccc.id
            SET ccc.servicios_extra_final = IFNULL(s.total_extra, 0)
            WHERE ccc.deleted_at IS NULL
              AND (
                    IFNULL(ccc.servicios_extra_final, 0) != IFNULL(s.total_extra, 0)
                 OR (s.total_extra IS NOT NULL AND IFNULL(ccc.servicios_extra_final, 0) = 0)
              )
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
