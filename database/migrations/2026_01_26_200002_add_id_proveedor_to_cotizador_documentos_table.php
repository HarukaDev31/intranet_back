<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documentos del cotizador pasan a ser por proveedor; id_proveedor nullable por compatibilidad con datos existentes.
     */
    public function up(): void
    {
        //drop column id_proveedor if exists
        Schema::table('contenedor_consolidado_cotizacion_cotizador_documentos', function (Blueprint $table) {
            $table->dropColumn('id_proveedor');
        });
        Schema::table('contenedor_consolidado_cotizacion_cotizador_documentos', function (Blueprint $table) {
            $table->unsignedInteger('id_proveedor')->nullable()->after('id_cotizacion');
            $table->foreign('id_proveedor', 'cc_cotizador_docs_id_proveedor_fk')
                ->references('id')->on('contenedor_consolidado_cotizacion_proveedores')->onDelete('cascade');
            $table->index('id_proveedor', 'cc_cotizador_docs_id_proveedor_idx');
        });
    }

    public function down(): void
    {
        Schema::table('contenedor_consolidado_cotizacion_cotizador_documentos', function (Blueprint $table) {
            $table->dropForeign('cc_cotizador_docs_id_proveedor_fk');
            $table->dropIndex('cc_cotizador_docs_id_proveedor_idx');
            $table->dropColumn('id_proveedor');
        });
    }
};
