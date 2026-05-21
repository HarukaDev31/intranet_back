<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDetalleJsonToConsolidadoPlantillaFinalBatchesTable extends Migration
{
    public function up()
    {
        Schema::table('consolidado_plantilla_final_batches', function (Blueprint $table) {
            $table->json('detalle_json')->nullable()->after('clientes_error');
        });
    }

    public function down()
    {
        Schema::table('consolidado_plantilla_final_batches', function (Blueprint $table) {
            $table->dropColumn('detalle_json');
        });
    }
}
