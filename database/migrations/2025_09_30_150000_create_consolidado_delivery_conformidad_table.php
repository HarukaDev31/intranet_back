<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsolidadoDeliveryConformidadTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consolidado_delivery_conformidad', function (Blueprint $table) {
            $table->id();

            // Relación con el contenedor y la cotización
            $table->unsignedInteger('id_contenedor');
            $table->integer('id_cotizacion');

            // Tipo de formulario: 0 = Provincia, 1 = Lima
            $table->unsignedTinyInteger('type_form')->comment('0=Provincia, 1=Lima');

            // Vinculación con el formulario (solo uno de los dos será no nulo según type_form)
            $table->unsignedBigInteger('id_form_lima')->nullable();
            $table->unsignedBigInteger('id_form_province')->nullable();

            // Dos fotos de conformidad (ruta/URL en el filesystem configurado)
            $table->string('photo_1_path');
            $table->string('photo_2_path');
            // Metadatos opcionales
            $table->string('photo_1_mime')->nullable();
            $table->string('photo_2_mime')->nullable();
            $table->unsignedInteger('photo_1_size')->nullable(); // bytes
            $table->unsignedInteger('photo_2_size')->nullable(); // bytes

            // Quién subió y cuándo
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->nullable();

            $table->timestamps();

            // Índices y llaves foráneas
            $table->foreign('id_contenedor')
                ->references('id')->on('carga_consolidada_contenedor')
                ->onDelete('cascade');

            $table->foreign('id_cotizacion')
                ->references('id')->on('contenedor_consolidado_cotizacion')
                ->onDelete('cascade');

            $table->foreign('id_form_lima')
                ->references('id')->on('consolidado_delivery_form_lima')
                ->onDelete('cascade');

            $table->foreign('id_form_province')
                ->references('id')->on('consolidado_delivery_form_province')
                ->onDelete('cascade');

            $table->foreign('uploaded_by')
                ->references('id')->on('users')
                ->onDelete('set null');

            // Evitar duplicados: una conformidad por cotización y tipo de formulario
            $table->unique(['id_cotizacion', 'type_form'], 'uq_cotizacion_type_form');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('consolidado_delivery_conformidad');
    }
}
