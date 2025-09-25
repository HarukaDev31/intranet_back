<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddConfirmationDateToContenedorConsolidadoCotizacionCoordinacionPagosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tableName = 'contenedor_consolidado_cotizacion_coordinacion_pagos';

        // 1. Crear columna solo si no existe (la migración previa pudo fallar tras crear la columna)
        if (!Schema::hasColumn($tableName, 'confirmation_date')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('confirmation_date')->nullable()->after('status');
            });
        }

        // 2. Crear índice corto solo si no existe (MySQL no tiene "create index if not exists")
        $indexName = 'ccc_pagos_conf_date_idx';
        $indexExists = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]);
        if (empty($indexExists)) {
            // Asegurar que la columna existe antes de crear el índice
            if (Schema::hasColumn($tableName, 'confirmation_date')) {
                DB::statement("CREATE INDEX `{$indexName}` ON `{$tableName}` (`confirmation_date`)");
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tableName = 'contenedor_consolidado_cotizacion_coordinacion_pagos';
        $indexName = 'ccc_pagos_conf_date_idx';

        // Drop index only if exists
        $indexExists = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]);
        if (!empty($indexExists)) {
            DB::statement("DROP INDEX `{$indexName}` ON `{$tableName}`");
        }

        if (Schema::hasColumn($tableName, 'confirmation_date')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('confirmation_date');
            });
        }
    }
}
