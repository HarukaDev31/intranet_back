<?php

use App\Enums\CargaConsolidada\ExcelSeguimientoLinkStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExcelSeguimientoLinkStatusEnum extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('carga_consolidada_contenedor', 'excel_seguimiento_link_status')) {
            return;
        }

        DB::table('carga_consolidada_contenedor')
            ->whereNotNull('excel_seguimiento_link_status')
            ->whereNotIn('excel_seguimiento_link_status', ExcelSeguimientoLinkStatus::ALL)
            ->update([
                'excel_seguimiento_link_status' => ExcelSeguimientoLinkStatus::FAILED,
                'excel_seguimiento_link_error' => 'Estado de vinculación inválido',
            ]);

        $enumValues = implode("','", ExcelSeguimientoLinkStatus::ALL);

        DB::statement(
            "ALTER TABLE `carga_consolidada_contenedor` "
            . "MODIFY `excel_seguimiento_link_status` ENUM('{$enumValues}') NULL"
        );
    }

    public function down()
    {
        if (!Schema::hasColumn('carga_consolidada_contenedor', 'excel_seguimiento_link_status')) {
            return;
        }

        DB::statement(
            'ALTER TABLE `carga_consolidada_contenedor` '
            . 'MODIFY `excel_seguimiento_link_status` VARCHAR(32) NULL'
        );
    }
}
