<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBdProductosRegulacionesDocumentosEspecialesMediaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bd_productos_regulaciones_documentos_especiales_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_regulacion')->comment('ID de la regulación de documentos especiales');
            $table->string('extension', 10)->comment('Extensión del archivo');
            $table->integer('peso')->comment('Peso del archivo en bytes');
            $table->string('nombre_original', 255)->comment('Nombre original del archivo');
            $table->string('ruta', 500)->comment('Ruta donde se guarda el archivo');
            $table->timestamp('created_at')->useCurrent()->comment('Fecha de creación');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Fecha de actualización');
            
            // Índices
            $table->index('id_regulacion', 'idx_id_regulacion');
            $table->index('created_at', 'idx_created_at');
            $table->index('extension', 'idx_extension');
            $table->index('peso', 'idx_peso');
            
            // Foreign key
            $table->foreign('id_regulacion', 'fk_documentos_media_regulacion')
                  ->references('id')
                  ->on('bd_productos_regulaciones_documentos_especiales')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bd_productos_regulaciones_documentos_especiales_media');
    }
}
