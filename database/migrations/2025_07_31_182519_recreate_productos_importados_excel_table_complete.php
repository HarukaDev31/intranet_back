<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RecreateProductosImportadosExcelTableComplete extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Eliminar la tabla si existe
        Schema::dropIfExists('productos_importados_excel');
        
        // Crear la tabla completa según el DDL
        Schema::create('productos_importados_excel', function (Blueprint $table) {
            $table->id();
            $table->integer('idContenedor');
            $table->string('item', 50)->nullable();
            $table->string('nombre_comercial', 255)->nullable();
            $table->text('foto')->nullable();
            $table->text('caracteristicas')->nullable();
            $table->string('rubro', 100)->nullable();
            $table->string('tipo_producto', 100)->nullable();
            $table->bigInteger('entidad_id')->unsigned()->nullable();
            $table->decimal('precio_exw', 12, 2)->nullable();
            $table->string('subpartida', 50)->nullable();
            $table->string('link', 255)->nullable();
            $table->string('unidad_comercial', 50)->nullable();
            $table->string('arancel_sunat', 50)->nullable();
            $table->string('arancel_tlc', 50)->nullable();
            $table->string('antidumping', 50)->nullable();
            $table->string('antidumping_value', 50)->nullable();
            $table->string('correlativo', 50)->nullable();
            $table->string('etiquetado', 255)->nullable();
            $table->bigInteger('tipo_etiquetado_id')->unsigned()->nullable();
            $table->string('doc_especial', 255)->nullable();
            $table->boolean('tiene_observaciones')->default(false);
            $table->text('observaciones')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();
            $table->enum('tipo', ['LIBRE', 'RESTRINGIDO'])->default('LIBRE');
            
            // Índices
            $table->index('entidad_id', 'productos_importados_excel_entidad_id_index');
            $table->index('tipo_etiquetado_id', 'productos_importados_excel_tipo_etiquetado_id_index');
            $table->index('tiene_observaciones', 'productos_importados_excel_tiene_observaciones_index');
            
            // Configuración del motor y charset
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('productos_importados_excel');
    }
}
