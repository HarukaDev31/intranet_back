<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateDocumentStatusEnumValues extends Migration
{
    /** @var string[] */
    private $columns = [
        'invoice_status',
        'packing_status',
        'excel_conf_status',
        'invoice_status_final',
        'packing_status_final',
        'excel_conf_status_final',
    ];

    /**
     * Renombra el estado 'Recibido' a 'Entregado' y agrega 'Solicitado' a los 5 estados
     * documentales: Pendiente, Solicitado, Entregado, Observado, Revisado.
     *
     * @return void
     */
    public function up()
    {
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode ?? '';
        DB::statement("SET SESSION sql_mode = ''");

        try {
            foreach ($this->columns as $column) {
                if (!Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', $column)) {
                    continue;
                }

                DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores MODIFY {$column} ENUM('Pendiente', 'Recibido', 'Observado', 'Revisado', 'Solicitado', 'Entregado') DEFAULT 'Pendiente'");

                DB::table('contenedor_consolidado_cotizacion_proveedores')
                    ->where($column, 'Recibido')
                    ->update([$column => 'Entregado']);

                DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores MODIFY {$column} ENUM('Pendiente', 'Solicitado', 'Entregado', 'Observado', 'Revisado') DEFAULT 'Pendiente'");
            }
        } finally {
            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }

    /**
     * @return void
     */
    public function down()
    {
        $originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode as mode')->mode ?? '';
        DB::statement("SET SESSION sql_mode = ''");

        try {
            foreach ($this->columns as $column) {
                if (!Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', $column)) {
                    continue;
                }

                DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores MODIFY {$column} ENUM('Pendiente', 'Recibido', 'Observado', 'Revisado', 'Solicitado', 'Entregado') DEFAULT 'Pendiente'");

                DB::table('contenedor_consolidado_cotizacion_proveedores')
                    ->where($column, 'Entregado')
                    ->update([$column => 'Recibido']);

                DB::table('contenedor_consolidado_cotizacion_proveedores')
                    ->where($column, 'Solicitado')
                    ->update([$column => 'Pendiente']);

                DB::statement("ALTER TABLE contenedor_consolidado_cotizacion_proveedores MODIFY {$column} ENUM('Pendiente', 'Recibido', 'Observado', 'Revisado') DEFAULT 'Pendiente'");
            }
        } finally {
            DB::statement("SET SESSION sql_mode = '{$originalSqlMode}'");
        }
    }
}
