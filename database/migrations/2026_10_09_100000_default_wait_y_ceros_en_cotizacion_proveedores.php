<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * contenedor_consolidado_cotizacion_proveedores: estados_proveedor nacía en NULL y qty_box /
 * cbm_total también (customers mostraba esos proveedores sin estado ni cantidades).
 *
 * - Default de columna: estados_proveedor = 'WAIT', qty_box = 0, cbm_total = 0.
 * - Cuadra la data existente: los NULL pasan a 'WAIT' / 0 / 0.
 *
 * El tipo de cada columna se lee de la propia tabla (enum/int/decimal) para no tocarlo.
 */
class DefaultWaitYCerosEnCotizacionProveedores extends Migration
{
    private const TABLA = 'contenedor_consolidado_cotizacion_proveedores';

    public function up()
    {
        if (!Schema::hasTable(self::TABLA)) {
            return;
        }

        $this->definirDefault('estados_proveedor', "'WAIT'");
        $this->definirDefault('qty_box', '0');
        $this->definirDefault('cbm_total', '0');

        DB::table(self::TABLA)->whereNull('estados_proveedor')->update(['estados_proveedor' => 'WAIT']);
        DB::table(self::TABLA)->whereNull('qty_box')->update(['qty_box' => 0]);
        DB::table(self::TABLA)->whereNull('cbm_total')->update(['cbm_total' => 0]);
    }

    public function down()
    {
        if (!Schema::hasTable(self::TABLA)) {
            return;
        }

        // Solo se restauran los defaults anteriores (NULL); la data corregida se conserva.
        $this->definirDefault('estados_proveedor', 'NULL');
        $this->definirDefault('qty_box', 'NULL');
        $this->definirDefault('cbm_total', 'NULL');
    }

    /**
     * @param string $columna
     * @param string $default Literal SQL (ej. "'WAIT'", "0", "NULL")
     */
    private function definirDefault($columna, $default)
    {
        if (!Schema::hasColumn(self::TABLA, $columna)) {
            return;
        }

        $info = null;
        foreach (DB::select('SHOW COLUMNS FROM `' . self::TABLA . '`') as $fila) {
            if ($fila->Field === $columna) {
                $info = $fila;
                break;
            }
        }
        if (!$info || empty($info->Type)) {
            return;
        }

        // Se conserva el tipo y la nulabilidad actuales; solo cambia el DEFAULT.
        $null = strtoupper((string) $info->Null) === 'YES' ? 'NULL' : 'NOT NULL';
        DB::statement(
            'ALTER TABLE `' . self::TABLA . '` MODIFY `' . $columna . '` ' . $info->Type . ' ' . $null . ' DEFAULT ' . $default
        );
    }
}
