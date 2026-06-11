<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExcelSeguimientoLinkStatusToContenedor extends Migration
{
    public function up()
    {
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            if (!Schema::hasColumn('carga_consolidada_contenedor', 'excel_seguimiento_link_status')) {
                $table->string('excel_seguimiento_link_status', 32)->nullable()->after('excel_seguimiento_file_name');
            }
            if (!Schema::hasColumn('carga_consolidada_contenedor', 'excel_seguimiento_link_error')) {
                $table->text('excel_seguimiento_link_error')->nullable()->after('excel_seguimiento_link_status');
            }
        });
    }

    public function down()
    {
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            foreach (['excel_seguimiento_link_status', 'excel_seguimiento_link_error'] as $col) {
                if (Schema::hasColumn('carga_consolidada_contenedor', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
