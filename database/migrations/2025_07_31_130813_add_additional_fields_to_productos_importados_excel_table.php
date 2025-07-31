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
        Schema::table('productos_importados_excel', function (Blueprint $table) {
            // Campos para antidumping
            $table->string('antidumping_value', 50)->nullable()->after('antidumping');
            
            // Campos para entidad reguladora
            $table->unsignedBigInteger('entidad_id')->nullable()->after('tipo_producto');
            
            // Campos para etiquetado especial
            $table->unsignedBigInteger('tipo_etiquetado_id')->nullable()->after('etiquetado');
            
            // Campos para observaciones
            $table->boolean('tiene_observaciones')->default(false)->after('doc_especial');
            $table->text('observaciones')->nullable()->after('tiene_observaciones');
            
            // Índices para mejorar rendimiento
            $table->index('entidad_id');
            $table->index('tipo_etiquetado_id');
            $table->index('tiene_observaciones');
        });
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
