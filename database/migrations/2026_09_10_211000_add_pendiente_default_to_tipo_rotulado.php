<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T.Rotulado socio: PENDIENTE | GENERAL (GENERAL = rotulado).
 * El enum nace en pendiente.
 *
 * ALTER TABLE revalida filas legacy con fechas '0000-00-00'; se relaja sql_mode
 * solo en esta sesión.
 */
class AddPendienteDefaultToTipoRotulado extends Migration
{
    private $table = 'contenedor_consolidado_cotizacion_proveedores';

    private $enumConPendiente = "ENUM('pendiente','rotulado','calzado','ropa','ropa_interior','maquinaria','movilidad_personal')";

    private $enumOriginal = "ENUM('rotulado','calzado','ropa','ropa_interior','maquinaria','movilidad_personal')";

    public function up()
    {
        if (!Schema::hasTable($this->table) || !Schema::hasColumn($this->table, 'tipo_rotulado')) {
            return;
        }

        $this->conSqlModeRelajado(function () {
            $this->limpiarFechasCero();
            DB::statement(
                "ALTER TABLE {$this->table} MODIFY COLUMN tipo_rotulado {$this->enumConPendiente} NULL DEFAULT 'pendiente' COMMENT 'Tipo de rotulado a aplicar al proveedor'"
            );
            DB::table($this->table)->whereNull('tipo_rotulado')->update(['tipo_rotulado' => 'pendiente']);
            DB::statement(
                "ALTER TABLE {$this->table} MODIFY COLUMN tipo_rotulado {$this->enumConPendiente} NOT NULL DEFAULT 'pendiente' COMMENT 'Tipo de rotulado a aplicar al proveedor'"
            );
        });
    }

    public function down()
    {
        if (!Schema::hasTable($this->table) || !Schema::hasColumn($this->table, 'tipo_rotulado')) {
            return;
        }

        $this->conSqlModeRelajado(function () {
            DB::table($this->table)->where('tipo_rotulado', 'pendiente')->update(['tipo_rotulado' => 'rotulado']);
            DB::statement(
                "ALTER TABLE {$this->table} MODIFY COLUMN tipo_rotulado {$this->enumOriginal} NULL COMMENT 'Tipo de rotulado a aplicar al proveedor'"
            );
        });
    }

    private function limpiarFechasCero()
    {
        foreach (['arrive_date_china', 'arrive_date'] as $columna) {
            if (!Schema::hasColumn($this->table, $columna)) {
                continue;
            }
            DB::statement(
                "UPDATE `{$this->table}` SET `{$columna}` = NULL WHERE `{$columna}` IN ('0000-00-00', '0000-00-00 00:00:00')"
            );
        }
    }

    private function conSqlModeRelajado($callback)
    {
        $sqlModeOriginal = DB::selectOne('SELECT @@SESSION.sql_mode as m')->m;
        DB::statement("SET SESSION sql_mode = ''");
        try {
            $callback();
        } finally {
            DB::statement("SET SESSION sql_mode = '" . str_replace("'", "''", (string) $sqlModeOriginal) . "'");
        }
    }
}
