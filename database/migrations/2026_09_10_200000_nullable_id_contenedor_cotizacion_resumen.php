<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una cotización resumen duplicada nace sin contenedor.
 * No se puede confirmar hasta asignarle un consolidado.
 */
class NullableIdContenedorCotizacionResumen extends Migration
{
    public function up()
    {
        $this->nullableUnsignedInt('contenedor_consolidado_cotizacion', 'id_contenedor');
        $this->nullableUnsignedInt('contenedor_consolidado_cotizacion_proveedores', 'id_contenedor');
        $this->nullableUnsignedInt('cotizacion_proveedor_resumen', 'id_contenedor');
        $this->nullableUnsignedInt('cotizacion_proveedor_archivo_ia', 'id_contenedor');
    }

    public function down()
    {
        // No se revierte: pueden existir filas sin contenedor.
    }

    private function nullableUnsignedInt($table, $column)
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $row = DB::selectOne(
            'SELECT IS_NULLABLE, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        if (!$row || strtoupper((string) $row->IS_NULLABLE) === 'YES') {
            return;
        }

        $type = $row->COLUMN_TYPE ?: 'int unsigned';
        DB::statement(sprintf(
            'ALTER TABLE `%s` MODIFY `%s` %s NULL',
            $table,
            $column,
            $type
        ));
    }
}
