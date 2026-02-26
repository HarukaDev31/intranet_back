<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comprobantes (factura/boleta electrónica) del perfil de Contabilidad.
     * Se sube el PDF/Word y un servicio de IA extrae tipo_comprobante y valor_comprobante.
     */
    public function up(): void
    {
        Schema::create('contenedor_consolidado_comprobantes', function (Blueprint $table) {
            $table->id();
            $table->integer('quotation_id');
            $table->string('tipo_comprobante', 50)->nullable();   // 'Factura', 'Boleta' — extraído por IA
            $table->decimal('valor_comprobante', 15, 2)->nullable(); // monto extraído por IA
            $table->string('file_name', 255)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->tinyInteger('extracted_by_ai')->default(0); // 1 si los datos fueron extraídos por Gemini
            $table->timestamps();

            $table->foreign('quotation_id', 'cc_comprobantes_quotation_id_fk')
                ->references('id')
                ->on('contenedor_consolidado_cotizacion')
                ->onDelete('cascade');

            $table->index('quotation_id', 'cc_comprobantes_quotation_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contenedor_consolidado_comprobantes');
    }
};
