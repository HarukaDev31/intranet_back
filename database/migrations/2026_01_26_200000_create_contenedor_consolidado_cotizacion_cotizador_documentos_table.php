<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documentos generales de la cotización (perfil cotizador): Proforma Invoice, Packing List, Ficha Técnica + custom.
     * Equivalente en uso a contenedor_consolidado_cotizacion_documentacion pero para la vista cotizador.
     */
    public function up(): void
    {
        Schema::create('contenedor_consolidado_cotizacion_cotizador_documentos', function (Blueprint $table) {
            $table->id();
            $table->integer('id_cotizacion');
            $table->string('tipo_documento', 100); // 'proforma_invoice', 'packing_list', 'ficha_tecnica' o nombre custom
            $table->string('folder_name', 255)->nullable();
            $table->string('file_url', 500)->nullable();
            $table->timestamps();

            $table->foreign('id_cotizacion', 'cc_cotizador_docs_id_cotizacion_fk')->references('id')->on('contenedor_consolidado_cotizacion')->onDelete('cascade');
            $table->index('id_cotizacion', 'cc_cotizador_docs_id_cotizacion_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contenedor_consolidado_cotizacion_cotizador_documentos');
    }
};
