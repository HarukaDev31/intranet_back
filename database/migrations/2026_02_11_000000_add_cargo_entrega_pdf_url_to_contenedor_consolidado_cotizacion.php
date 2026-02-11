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
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'cargo_entrega_pdf_url')) {
                $table->text('cargo_entrega_pdf_url')->nullable()->comment('Ruta relativa en storage del PDF de cargo de entrega (ej: entregas/cargo_entrega/123/CARGO_ENTREGA_XXX.pdf)');
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
            if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'cargo_entrega_pdf_url')) {
                $table->dropColumn('cargo_entrega_pdf_url');
            }
        });
    }
};
