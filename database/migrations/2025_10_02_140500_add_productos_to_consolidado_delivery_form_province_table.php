<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductosToConsolidadoDeliveryFormProvinceTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consolidado_delivery_form_province', function (Blueprint $table) {
            // Agregamos como NOT NULL; se define default '' para evitar fallos si ya hay registros existentes
            $table->string('productos', 255)->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consolidado_delivery_form_province', function (Blueprint $table) {
            $table->dropColumn('productos');
        });
    }
}
