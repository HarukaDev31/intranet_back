<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddLoadedToContenedorProveedorEstadosTrackingEnum extends Migration
{
    /**
     * Estados que la app inserta en tracking y pueden faltar en el ENUM de prod.
     *
     * @var string[]
     */
    private $needed = array(
        'LOADED',
        'ROTULADO',
        'RESERVADO',
        'COBRANDO',
        'DATOS PROVEEDOR',
        'INSPECCIONADO',
        'NO RESERVADO',
        'EMBARCADO',
        'NO EMBARCADO',
        'NC',
        'C',
        'R',
        'NS',
        'NP',
        'WAIT',
        'NO LOADED',
        'INSPECTION',
    );

    /**
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('contenedor_proveedor_estados_tracking')) {
            return;
        }

        $columnInfo = DB::select("SHOW COLUMNS FROM contenedor_proveedor_estados_tracking LIKE 'estado'");
        if (empty($columnInfo)) {
            return;
        }

        $type = (string) $columnInfo[0]->Type;
        if (stripos($type, 'enum(') !== 0) {
            return;
        }

        preg_match("/enum\((.*)\)/i", $type, $matches);
        if (empty($matches[1])) {
            return;
        }

        $current = array_map('trim', explode(',', str_replace("'", '', $matches[1])));
        $merged = $current;
        foreach ($this->needed as $state) {
            if (!in_array($state, $merged, true)) {
                $merged[] = $state;
            }
        }

        if ($merged === $current) {
            return;
        }

        $newEnum = "ENUM('" . implode("', '", $merged) . "')";
        DB::statement("ALTER TABLE contenedor_proveedor_estados_tracking MODIFY COLUMN estado {$newEnum} NULL");
    }

    /**
     * @return void
     */
    public function down()
    {
        // No se revierte: puede haber filas con LOADED u otros valores nuevos.
    }
}
