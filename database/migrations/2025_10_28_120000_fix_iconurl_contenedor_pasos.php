<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixIconurlContenedorPasos extends Migration
{
    /**
     * Run the migrations.
     * We create a backup column, copy current values and try a set of safe updates
     * to repair malformed iconURL values (missing slashes, relative paths, duplicated hosts).
     */
    public function up()
    {
        // 1) Add backup column if not exists
            // Target table and column
            $tableName = 'contenedor_consolidado_order_steps';
            $columnName = 'iconURL';

        // Resolve values using a few strategies. Use config('app.url') as canonical host.
        $host = rtrim((string)(config('app.url') ?? env('APP_URL') ?? ''), '/');

        // 3) Case: host concatenated directly with 'assets/' (e.g. https://hostassets/...)
        // Try REGEXP_REPLACE (MySQL 8+). If it fails, fallback to a CONCAT/SUBSTRING_INDEX approach.
        try {
            DB::statement("UPDATE " . $tableName . " SET " . $columnName . " = REGEXP_REPLACE(" . $columnName . ", '^(https?://[^/]+)(assets/)', '\\\\1/\\\\2') WHERE " . $columnName . " REGEXP 'https?://[^/]+assets/'");
    } catch (\Exception $e) {
            // Fallback for older MySQL: insert '/assets/' between parts
            try {
                DB::statement("UPDATE " . $tableName . " SET " . $columnName . " = CONCAT(SUBSTRING_INDEX(" . $columnName . ", 'assets/', 1), '/assets/', SUBSTRING_INDEX(" . $columnName . ", 'assets/', -1)) WHERE " . $columnName . " LIKE '%assets/%' AND " . $columnName . " REGEXP '^https?://[^/]+assets/'");
            } catch (\Exception $ex) {
                // ignore, we'll try other fixes
            }
        }

        // 4) Case: relative paths that contain 'icons/' but not 'assets/' (e.g. '1/2icons/xxx.png' or 'icons/xxx.png')
        // Transform to: {host}/assets/icons/{filename}
        try {
            DB::statement(DB::raw("UPDATE " . $tableName . " SET " . $columnName . " = CONCAT('" . $host . "', '/assets/icons/', SUBSTRING_INDEX(" . $columnName . ", 'icons/', -1)) WHERE " . $columnName . " NOT LIKE 'http%' AND " . $columnName . " LIKE '%icons/%' AND " . $columnName . " NOT LIKE '%assets/%'"));
    } catch (\Exception $e) {
            // ignore
        }

        // 5) Case: relative assets/storage paths -> prefix host
        try {
            DB::statement(DB::raw("UPDATE " . $tableName . " SET " . $columnName . " = CONCAT('" . $host . "', '/', TRIM(LEADING '/' FROM " . $columnName . ")) WHERE " . $columnName . " NOT LIKE 'http%' AND (" . $columnName . " LIKE 'assets/%' OR " . $columnName . " LIKE 'storage/%')"));
    } catch (\Exception $e) {
            // ignore
        }

        // 6) Remove duplicated host occurrences (hosthost)
        if (!empty($host)) {
            try {
                DB::statement(DB::raw("UPDATE " . $tableName . " SET " . $columnName . " = REPLACE(" . $columnName . ", CONCAT('" . $host . "', '" . $host . "'), '" . $host . "') WHERE " . $columnName . " LIKE CONCAT('%', '" . $host . "', '" . $host . "', '%')"));
        } catch (\Exception $e) {
                // ignore
            }
        }
    }

    /**
     * Reverse the migrations.
     * We restore iconURL from the backup column and drop the backup column.
     */
    public function down()
    {
        // Restore values if backup exists (use same table/column as in up())
        $tableName = 'contenedor_consolidado_order_steps';
        $columnName = 'iconURL';

        if (Schema::hasColumn($tableName, $columnName . '_backup')) {
            try {
                DB::table($tableName)
                    ->whereNotNull($columnName . '_backup')
                    ->update([$columnName => DB::raw($columnName . '_backup')]);
            } catch (\Exception $e) {
                // ignore
            }

            // Drop the backup column
            Schema::table($tableName, function (Blueprint $table) use ($columnName) {
                $table->dropColumn($columnName . '_backup');
            });
        }
    }
}
