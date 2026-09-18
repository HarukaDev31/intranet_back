<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas del pipeline de Carga Consolidada que aun no tienen organizacion_id.
     * carga_consolidada_contenedor y contenedor_consolidado_cotizacion ya se
     * migraron aparte (la primera antes, la segunda queda cubierta aqui).
     */
    private array $tablas = [
        'contenedor_consolidado_cotizacion',
        'contenedor_consolidado_cotizacion_proveedores',
        'contenedor_consolidado_order_steps',
        'carga_consolidada_contenedor_tc_yuan',
        'contenedor_consolidado_documentacion_files',
        'contenedor_consolidado_documentacion_folders',
        'contenedor_proveedor_arrive_date_history',
        'contenedor_proveedor_estados_proveedor_history',
        'contenedor_seguimiento_corte_periodos',
        'contenedor_seguimiento_drive_cells',
        'contenedor_seguimiento_drive_cell_history',
        'contenedor_seguimiento_drive_snapshots',
        'contenedor_seguimiento_row_sync',
        'consolidado_delivery_form_lima',
        'consolidado_delivery_form_province',
        'boletin_quimico_cotizacion_item',
        'contenedor_consolidado_cotizacion_proveedores_items',
        'consolidado_factura_comercial_batches',
        'consolidado_plantilla_final_batches',
        'consolidado_comprobante_forms',
        'contenedor_consolidado_comprobantes',
        'contenedor_consolidado_cotizacion_cotizador_documentos',
        'contenedor_consolidado_cotizacion_cotizador_proveedor_documentos',
        'contenedor_consolidado_cotizacion_delivery_servicio',
        'contenedor_consolidado_cotizacion_documentacion',
        'contenedor_consolidado_detracciones',
        'contenedor_consolidado_facturas_e',
        'contenedor_consolidado_guias_remision',
        'contenedor_consolidado_cotizacion_coordinacion_pagos',
        'contenedor_proveedor_estados_tracking_estados',
        'contenedor_consolidado_almacen_documentacion',
        'contenedor_consolidado_almacen_inspection',
        'contenedor_consolidado_documentacion_observaciones',
        'contenedor_consolidado_cotizacion_proveedores_items_excel_conf',
        'contenedor_seguimiento_corte_clientes',
        'consolidado_cotizacion_aduana_tramites',
        'pagos_boletin_quimico',
    ];

    public function up(): void
    {
        // Varias tablas legacy tienen fechas '0000-00-00' preexistentes en
        // columnas ajenas a este cambio. ADD FOREIGN KEY fuerza una reescritura
        // de tabla que revalida esas filas contra el sql_mode actual. Se relaja
        // solo en esta sesion/migracion, no globalmente.
        $sqlModeOriginal = DB::selectOne('SELECT @@SESSION.sql_mode as m')->m;
        DB::statement("SET SESSION sql_mode = ''");

        try {
            $this->agregarColumnas();
            $this->backfill();
        } finally {
            DB::statement("SET SESSION sql_mode = '{$sqlModeOriginal}'");
        }
    }

    private function agregarColumnas(): void
    {
        foreach ($this->tablas as $tabla) {
            if (!Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'organizacion_id')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $table->unsignedInteger('organizacion_id')->nullable()->after('id');
                $table->index('organizacion_id', $this->indexName($tabla));
                $table->foreign('organizacion_id', $this->fkName($tabla))
                    ->references('ID_Organizacion')
                    ->on('organizacion')
                    ->onDelete('restrict');
            });
        }
    }

    private function backfill(): void
    {
        // Hoy solo existe una organizacion, asi que no hace falta reconstruir
        // la cadena de joins hasta el contenedor -- es el unico valor posible
        // para toda la data historica.
        $organizacionActual = DB::table('organizacion')->orderBy('ID_Organizacion')->value('ID_Organizacion');
        if ($organizacionActual === null) {
            return;
        }

        foreach ($this->tablas as $tabla) {
            if (!Schema::hasTable($tabla) || !Schema::hasColumn($tabla, 'organizacion_id')) {
                continue;
            }

            DB::table($tabla)
                ->whereNull('organizacion_id')
                ->update(['organizacion_id' => $organizacionActual]);
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            if (!Schema::hasTable($tabla) || !Schema::hasColumn($tabla, 'organizacion_id')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $table->dropForeign($this->fkName($tabla));
                $table->dropIndex($this->indexName($tabla));
                $table->dropColumn('organizacion_id');
            });
        }
    }

    private function indexName(string $tabla): string
    {
        return 'idx_' . $this->shortHash($tabla) . '_organizacion_id';
    }

    private function fkName(string $tabla): string
    {
        return 'fk_' . $this->shortHash($tabla) . '_organizacion_id';
    }

    /**
     * MySQL limita identificadores a 64 caracteres; varios nombres de tabla
     * de este modulo ya son largos, asi que se deriva un sufijo corto y
     * estable en vez de concatenar el nombre completo de la tabla.
     */
    private function shortHash(string $tabla): string
    {
        return substr(md5($tabla), 0, 12);
    }
};
