<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nuevo estado de proveedor China "WN" (wrong number).
 * Se agrega al ENUM de estados_proveedor y al del tracking, conservando el resto de valores,
 * el NULL/default y el comentario actuales de cada columna.
 */
class AddWnToEstadosProveedorEnums extends Migration
{
    private $columnas = array(
        array('contenedor_consolidado_cotizacion_proveedores', 'estados_proveedor'),
        array('contenedor_proveedor_estados_tracking', 'estado'),
    );

    public function up()
    {
        foreach ($this->columnas as $c) {
            $this->agregarValor($c[0], $c[1]);
        }
    }

    public function down()
    {
        // No se revierte: puede haber filas con WN.
    }

    private function agregarValor($tabla, $columna)
    {
        if (!Schema::hasTable($tabla)) {
            return;
        }

        $info = DB::select("SHOW FULL COLUMNS FROM {$tabla} LIKE '{$columna}'");
        if (empty($info)) {
            return;
        }

        $type = (string) $info[0]->Type;
        if (stripos($type, 'enum(') !== 0 || preg_match("/'WN'/i", $type)) {
            return;
        }

        $nuevo = preg_replace('/\)\s*$/', ",'WN')", $type);
        $null = strtoupper((string) $info[0]->Null) === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $info[0]->Default;
        $defaultSql = $default !== null ? " DEFAULT " . DB::getPdo()->quote($default) : ($null === 'NULL' ? ' DEFAULT NULL' : '');
        $comment = (string) $info[0]->Comment;
        $commentSql = $comment !== '' ? ' COMMENT ' . DB::getPdo()->quote($comment) : '';

        DB::statement("ALTER TABLE {$tabla} MODIFY COLUMN {$columna} {$nuevo} {$null}{$defaultSql}{$commentSql}");
    }
}
