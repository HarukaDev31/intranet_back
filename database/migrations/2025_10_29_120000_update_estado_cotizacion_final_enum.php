<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateEstadoCotizacionFinalEnum extends Migration
{
    /**
     * Run the migrations.
     *
     * Steps:
     * 1) Add new column estado_cotizacion_final2 with the extended enum values
     * 2) Copy values from estado_cotizacion_final -> estado_cotizacion_final2
     * 3) Drop the old estado_cotizacion_final column
     * 4) Rename estado_cotizacion_final2 to estado_cotizacion_final (keeping the extended enum)
     *
     * NOTE: This operation is destructive if something goes wrong. Backup DB before running.
     */
    public function up()
    {
        // Enum values: keep existing ones and add COBRANDO and PAGADO_V
        $enum = "'PENDIENTE','C.FINAL','AJUSTADO','COTIZADO','COBRANDO','PAGADO','SOBREPAGO','PAGADO_V'";

        // 1) Add new column
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            // Use raw statement because some environments may not support ->enum alteration without doctrine/dbal
            $table->enum('estado_cotizacion_final2', ['PENDIENTE','C.FINAL','AJUSTADO','COTIZADO','COBRANDO','PAGADO','SOBREPAGO','PAGADO_V'])->nullable();
        });

        // 2) Copy data from old column into new column
        DB::statement('UPDATE `contenedor_consolidado_cotizacion` SET `estado_cotizacion_final2` = `estado_cotizacion_final`');

        // 3) Drop the old column
        DB::statement('ALTER TABLE `contenedor_consolidado_cotizacion` DROP COLUMN `estado_cotizacion_final`');

        // 4) Rename the new column to original name and keep enum definition
        DB::statement("ALTER TABLE `contenedor_consolidado_cotizacion` CHANGE `estado_cotizacion_final2` `estado_cotizacion_final` ENUM($enum) NULL DEFAULT NULL");
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
