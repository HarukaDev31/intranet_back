<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateEstadoCotizacionFinalDefaultPending extends Migration
{
    /**
     * Run the migrations.
     *
     * Steps (same as previous migration):
     * 1) Add new column estado_cotizacion_final2 with the extended enum values and default PENDIENTE
     * 2) Copy values from estado_cotizacion_final -> estado_cotizacion_final2
     * 3) Drop the old estado_cotizacion_final column
     * 4) Rename estado_cotizacion_final2 to estado_cotizacion_final (keeping the extended enum and DEFAULT 'PENDIENTE')
     * 5) Normalize existing NULL/empty values to 'PENDIENTE'
     *
     * NOTE: Backup DB before running. This alters enum type and defaults.
     */
    public function up()
    {
    // Enum values: final set WITHOUT 'C.FINAL' (we normalize existing 'C.FINAL' values to 'PENDIENTE' first)
    $enum = "'PENDIENTE','AJUSTADO','COTIZADO','COBRANDO','PAGADO','SOBREPAGO'";

        // Normalize rows that may contain the legacy 'C.FINAL' value to 'PENDIENTE'
        DB::statement("UPDATE `contenedor_consolidado_cotizacion` SET `estado_cotizacion_final` = 'PENDIENTE' WHERE `estado_cotizacion_final` = 'C.FINAL'");

        // 1) Add new column with default 'PENDIENTE' (note: enum WITHOUT 'C.FINAL')
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->enum('estado_cotizacion_final2', ['PENDIENTE','AJUSTADO','COTIZADO','COBRANDO','PAGADO','SOBREPAGO'])->nullable()->default('PENDIENTE');
        });

        // 2) Copy data from old column into new column
        DB::statement('UPDATE `contenedor_consolidado_cotizacion` SET `estado_cotizacion_final2` = `estado_cotizacion_final`');

        // 3) Drop the old column
        DB::statement('ALTER TABLE `contenedor_consolidado_cotizacion` DROP COLUMN `estado_cotizacion_final`');

        // 4) Rename the new column to original name and keep enum definition and default 'PENDIENTE'
        DB::statement("ALTER TABLE `contenedor_consolidado_cotizacion` CHANGE `estado_cotizacion_final2` `estado_cotizacion_final` ENUM($enum) NULL DEFAULT 'PENDIENTE'");

        // 5) Normalize existing NULL or empty values to 'PENDIENTE'
        DB::statement("UPDATE `contenedor_consolidado_cotizacion` SET `estado_cotizacion_final` = 'PENDIENTE' WHERE `estado_cotizacion_final` IS NULL OR `estado_cotizacion_final` = ''");
    }

    /**
     * Reverse the migrations.
     *
     * Note: Automatic rollback may lose values that use the newly-added enum options.
     */
    public function down()
    {
        throw new \RuntimeException('This migration is not reversible automatically. To rollback, manually recreate the previous enum without COBRANDO and PAGADO_V and migrate values as needed.');
    }
}
