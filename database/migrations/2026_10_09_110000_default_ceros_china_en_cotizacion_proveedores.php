<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * contenedor_consolidado_cotizacion_proveedores: qty_box_china y cbm_total_china nacían en NULL.
 *
 * - Default de columna: 0.
 * - Cuadra la data existente: los NULL pasan a 0.
 *
 * Las consultas que usaban COALESCE(china, valor cotizado) como fallback ahora tratan 0 como
 * "sin dato de China" (NULLIF(x, 0)), así los totales no cambian.
 * El tipo de cada columna se lee de la propia tabla para no tocarlo.
 */
class DefaultCerosChinaEnCotizacionProveedores extends Migration
{
    private const TABLA = 'contenedor_consolidado_cotizacion_proveedores';

    public function up()
    {
        if (!Schema::hasTable(self::TABLA)) {
            return;
        }

        $this->definirDefault('qty_box_china', '0');
        $this->definirDefault('cbm_total_china', '0');

        DB::table(self::TABLA)->whereNull('qty_box_china')->update(['qty_box_china' => 0]);
        DB::table(self::TABLA)->whereNull('cbm_total_china')->update(['cbm_total_china' => 0]);
    }

    public function down()
    {
        if (!Schema::hasTable(self::TABLA)) {
            return;
        }

        // Solo se restauran los defaults anteriores (NULL); la data corregida se conserva.
        $this->definirDefault('qty_box_china', 'NULL');
        $this->definirDefault('cbm_total_china', 'NULL');
    }

    /**
     * @param string $columna
     * @param string $default Literal SQL (ej. "0", "NULL")
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

        $null = strtoupper((string) $info->Null) === 'YES' ? 'NULL' : 'NOT NULL';
        DB::statement(
            'ALTER TABLE `' . self::TABLA . '` MODIFY `' . $columna . '` ' . $info->Type . ' ' . $null . ' DEFAULT ' . $default
        );
    }
}
