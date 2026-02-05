<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserCotizacionExportsTable extends Migration
{
    /**
     * Run the migrations.
     * Registro de exportaciones de cotización (n8n / WhatsApp): solo genera PDF/Excel sin guardar en calculadora.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_cotizacion_exports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('Usuario que solicitó la exportación si hay auth');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('file_path', 500)->nullable()->comment('Ruta relativa del PDF en storage');
            $table->string('file_url', 500)->nullable()->comment('URL pública del PDF');
            $table->string('excel_path', 500)->nullable()->comment('Ruta relativa del Excel en storage');
            $table->string('excel_url', 500)->nullable()->comment('URL pública del Excel');
            $table->string('cliente_nombre', 255)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_cotizacion_exports');
    }
}
