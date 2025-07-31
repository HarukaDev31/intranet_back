<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAdditionalFieldsToProductosImportadosExcelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('productos_importados_excel', function (Blueprint $table) {
            $table->dropIndex(['entidad_id']);
            $table->dropIndex(['tipo_etiquetado_id']);
            $table->dropIndex(['tiene_observaciones']);
            
            $table->dropColumn([
                'antidumping_value',
                'entidad_id',
                'tipo_etiquetado_id',
                'tiene_observaciones',
                'observaciones'
            ]);
        });
    }
}
