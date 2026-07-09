<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inferir fecha_confirmacion desde la primera notificación "Cotización Confirmada".
     */
    public function up(): void
    {
        if (! Schema::hasTable('contenedor_consolidado_cotizacion')
            || ! Schema::hasTable('notificaciones')) {
            return;
        }

        if (! Schema::hasColumn('contenedor_consolidado_cotizacion', 'fecha_confirmacion')) {
            return;
        }

        $deletedClause = Schema::hasColumn('contenedor_consolidado_cotizacion', 'deleted_at')
            ? 'AND c.deleted_at IS NULL'
            : '';

        DB::statement("
            UPDATE contenedor_consolidado_cotizacion c
            INNER JOIN (
                SELECT referencia_id, MIN(created_at) AS fecha_desde_notificacion
                FROM notificaciones
                WHERE referencia_tipo = 'cotizacion'
                  AND titulo = 'Cotización Confirmada'
                  AND modulo = 'CargaConsolidada'
                GROUP BY referencia_id
            ) n ON n.referencia_id = c.id
            SET c.fecha_confirmacion = n.fecha_desde_notificacion
            WHERE c.estado_cotizador = 'CONFIRMADO'
              AND c.fecha_confirmacion IS NULL
              {$deletedClause}
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('contenedor_consolidado_cotizacion')
            || ! Schema::hasTable('notificaciones')) {
            return;
        }

        if (! Schema::hasColumn('contenedor_consolidado_cotizacion', 'fecha_confirmacion')) {
            return;
        }

        $deletedClause = Schema::hasColumn('contenedor_consolidado_cotizacion', 'deleted_at')
            ? 'AND c.deleted_at IS NULL'
            : '';

        DB::statement("
            UPDATE contenedor_consolidado_cotizacion c
            INNER JOIN (
                SELECT referencia_id, MIN(created_at) AS fecha_desde_notificacion
                FROM notificaciones
                WHERE referencia_tipo = 'cotizacion'
                  AND titulo = 'Cotización Confirmada'
                  AND modulo = 'CargaConsolidada'
                GROUP BY referencia_id
            ) n ON n.referencia_id = c.id
            SET c.fecha_confirmacion = NULL
            WHERE c.estado_cotizador = 'CONFIRMADO'
              AND c.fecha_confirmacion = n.fecha_desde_notificacion
              {$deletedClause}
        ");
    }
};
