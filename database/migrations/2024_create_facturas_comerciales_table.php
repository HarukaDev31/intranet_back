<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('contenedor_consolidado_facturas_e');
        Schema::create('contenedor_consolidado_facturas_e', function (Blueprint $table) {
            $table->id();
            $table->integer('quotation_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('quotation_id')
                  ->references('id')
                  ->on('contenedor_consolidado_cotizacion')
                  ->onDelete('cascade');

            // Index for fast lookups
            $table->index('quotation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contenedor_consolidado_facturas_electronicas');
    }
};

