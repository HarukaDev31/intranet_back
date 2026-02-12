<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documentos por proveedor (perfil cotizador): hasta 4 por proveedor (tipo "Imágenes").
     * Relación equivalente a contenedor_consolidado_cotizacion_proveedores + documentación por proveedor.
     */
    public function up(): void
    {
        Schema::create('contenedor_consolidado_cotizacion_cotizador_proveedor_documentos', function (Blueprint $table) {
            $table->id();
            $table->integer('id_cotizacion');
            $table->unsignedInteger('id_proveedor');
            $table->string('file_url', 500)->nullable();
            $table->unsignedTinyInteger('orden')->default(1); // 1-4 para máximo 4 por proveedor
            $table->timestamps();

            $table->foreign('id_cotizacion', 'cc_cotizador_prov_docs_id_cotizacion_fk')->references('id')->on('contenedor_consolidado_cotizacion')->onDelete('cascade');
            $table->foreign('id_proveedor', 'cc_cotizador_prov_docs_id_proveedor_fk')->references('id')->on('contenedor_consolidado_cotizacion_proveedores')->onDelete('cascade');
            $table->index(['id_cotizacion', 'id_proveedor'], 'cc_cotizador_prov_docs_cot_prov_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contenedor_consolidado_cotizacion_cotizador_proveedor_documentos');
    }
};
