<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('contenedor_consolidado_cotizacion_proveedores')) {
            $tableName = 'contenedor_consolidado_cotizacion_proveedores';

            // If the column doesn't exist, add it as DATE
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'arrive_date')) {
                    $table->date('arrive_date')->nullable()->after('arrive_date_china');
                }
            });

            // If the column exists but is a DATETIME/TIMESTAMP, convert it to DATE safely.
            if (Schema::hasColumn($tableName, 'arrive_date')) {
                $dbName = DB::getDatabaseName();
                $col = DB::selectOne(
                    'SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                    [$dbName, $tableName, 'arrive_date']
                );

                if ($col && in_array(strtolower($col->DATA_TYPE), ['datetime', 'timestamp'])) {
                    // Temporarily remove NO_ZERO_DATE/NO_ZERO_IN_DATE to allow sanitization
                    $origModeRow = DB::select("SELECT @@SESSION.sql_mode as mode");
                    $origMode = is_array($origModeRow) && isset($origModeRow[0]->mode) ? $origModeRow[0]->mode : '';
                    $newMode = str_replace(["NO_ZERO_DATE", "NO_ZERO_IN_DATE"], '', $origMode);
                    $newMode = preg_replace('/,{2,}/', ',', $newMode);
                    $newMode = trim($newMode, ', ');
                    try {
                        DB::statement("SET SESSION sql_mode = '" . ($newMode ?? '') . "'");

                        // Sanitize zero-date sentinel values that would block ALTER
                        DB::statement("UPDATE `{$tableName}` SET `arrive_date` = NULL WHERE `arrive_date` IN ('0000-00-00','0000-00-00 00:00:00')");

                        // Convert to DATE using raw SQL to avoid doctrine/dbal requirement
                        DB::statement("ALTER TABLE `{$tableName}` MODIFY `arrive_date` DATE NULL AFTER `arrive_date_china`");
                    } finally {
                        // Restore original sql_mode
                        DB::statement("SET SESSION sql_mode = '" . ($origMode ?? '') . "'");
                    }
                }
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
        if (Schema::hasTable('contenedor_consolidado_cotizacion_proveedores')) {
            Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
                if (Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'arrive_date')) {
                    $table->dropColumn('arrive_date');
                }
            });
        }
    }
};
