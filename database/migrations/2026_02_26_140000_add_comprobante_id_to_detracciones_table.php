<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddComprobanteIdToDetraccionesTable extends Migration
{
    public function up()
    {
        Schema::table('contenedor_consolidado_detracciones', function (Blueprint $table) {
            // Vincula la constancia de pago al comprobante que generó la detracción
            $table->unsignedInteger('comprobante_id')->nullable()->after('quotation_id');
        });
    }

    public function down()
    {
        Schema::table('contenedor_consolidado_detracciones', function (Blueprint $table) {
            $table->dropColumn('comprobante_id');
        });
    }
}
