<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para el endpoint público JSON de exportación de cotizaciones (third-party).
 * Idempotente: no falla si el índice ya existe.
 */
class AddThirdPartyCotizacionExportIndexes extends Migration
{
    public function up()
    {
        $this->addCotizacionExportIndexes();
        $this->addProveedorExportIndexes();
    }

    public function down()
    {
        $this->dropIndexIfExists('contenedor_consolidado_cotizacion', 'cc_cot_third_party_export_idx');
        $this->dropIndexIfExists('contenedor_consolidado_cotizacion_proveedores', 'cc_prov_cotizacion_estados_idx');
    }

    private function addCotizacionExportIndexes()
    {
        if (!Schema::hasTable('contenedor_consolidado_cotizacion')) {
            return;
        }

        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'id_contenedor')
            && Schema::hasColumn('contenedor_consolidado_cotizacion', 'id_cliente_importacion')
            && !$this->indexExists('contenedor_consolidado_cotizacion', 'cc_cot_third_party_export_idx')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->index(
                    ['id_contenedor', 'id_cliente_importacion', 'id'],
                    'cc_cot_third_party_export_idx'
                );
            });
        }
    }

    private function addProveedorExportIndexes()
    {
        if (!Schema::hasTable('contenedor_consolidado_cotizacion_proveedores')) {
            return;
        }

        if (Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'id_cotizacion')
            && Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'estados')
            && Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'estados_proveedor')
            && !$this->indexExists('contenedor_consolidado_cotizacion_proveedores', 'cc_prov_cotizacion_estados_idx')) {
            Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
                $table->index(
                    ['id_cotizacion', 'estados', 'estados_proveedor'],
                    'cc_prov_cotizacion_estados_idx'
                );
            });
        }
    }

    /**
     * @param  string  $table
     * @param  string  $indexName
     * @return bool
     */
    private function indexExists($table, $indexName)
    {
        $database = Schema::getConnection()->getDatabaseName();
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$database, $table, $indexName]
        );

        return count($rows) > 0;
    }

    /**
     * @param  string  $table
     * @param  string  $indexName
     */
    private function dropIndexIfExists($table, $indexName)
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }
}
