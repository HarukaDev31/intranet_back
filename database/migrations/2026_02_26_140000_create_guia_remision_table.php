<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Múltiples guías de remisión por cotización.
     */
    public function up(): void
    {
        // Eliminar la tabla si existe
        Schema::dropIfExists('contenedor_consolidado_guia_remision');

        Schema::create('contenedor_consolidado_guia_remision', function (Blueprint $table) {
            $table->id();
            $table->integer('quotation_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();

            $table->foreign('quotation_id')
                ->references('id')
                ->on('contenedor_consolidado_cotizacion')
                ->onDelete('cascade');

            $table->index('quotation_id');
        });

        // Migrar guía única existente (guia_remision_url) a la nueva tabla
        $rows = DB::table('contenedor_consolidado_cotizacion')
            ->whereNotNull('guia_remision_url')
            ->where('guia_remision_url', '!=', '')
            ->select('id', 'guia_remision_url')
            ->get();
        foreach ($rows as $row) {
            $filePath = 'cargaconsolidada/guiaremision/' . $row->id . '/' . $row->guia_remision_url;
            DB::table('contenedor_consolidado_guia_remision')->insert([
                'quotation_id' => $row->id,
                'file_name'    => $row->guia_remision_url,
                'file_path'    => $filePath,
                'size'         => null,
                'mime_type'    => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contenedor_consolidado_guia_remision');
    }
};
