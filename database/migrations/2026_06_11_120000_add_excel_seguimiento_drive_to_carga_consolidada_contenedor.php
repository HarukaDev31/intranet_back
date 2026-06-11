<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExcelSeguimientoDriveToCargaConsolidadaContenedor extends Migration
{
    public function up()
    {
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            if (!Schema::hasColumn('carga_consolidada_contenedor', 'excel_seguimiento_drive_file_id')) {
                $table->string('excel_seguimiento_drive_file_id', 128)->nullable()->after('fecha_documentacion_max');
            }
            if (!Schema::hasColumn('carga_consolidada_contenedor', 'excel_seguimiento_drive_link')) {
                $table->string('excel_seguimiento_drive_link', 1024)->nullable()->after('excel_seguimiento_drive_file_id');
            }
            if (!Schema::hasColumn('carga_consolidada_contenedor', 'excel_seguimiento_vinculado_at')) {
                $table->timestamp('excel_seguimiento_vinculado_at')->nullable()->after('excel_seguimiento_drive_link');
            }
            if (!Schema::hasColumn('carga_consolidada_contenedor', 'excel_seguimiento_file_name')) {
                $table->string('excel_seguimiento_file_name', 255)->nullable()->after('excel_seguimiento_vinculado_at');
            }
        });
    }

    public function down()
    {
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            $cols = [
                'excel_seguimiento_drive_file_id',
                'excel_seguimiento_drive_link',
                'excel_seguimiento_vinculado_at',
                'excel_seguimiento_file_name',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('carga_consolidada_contenedor', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
