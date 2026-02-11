<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'cargo_entrega_pdf_firmado_url')) {
                $table->text('cargo_entrega_pdf_firmado_url')->nullable()->comment('Ruta relativa en storage del PDF de cargo de entrega firmado');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'cargo_entrega_pdf_firmado_url')) {
                $table->dropColumn('cargo_entrega_pdf_firmado_url');
            }
        });
    }
};
