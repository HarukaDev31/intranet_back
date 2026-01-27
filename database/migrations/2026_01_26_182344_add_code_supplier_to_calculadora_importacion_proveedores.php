<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCodeSupplierToCalculadoraImportacionProveedores extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('calculadora_importacion_proveedores', function (Blueprint $table) {
            $table->string('code_supplier', 50)->nullable()->after('qty_caja');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('calculadora_importacion_proveedores', function (Blueprint $table) {
            $table->dropColumn('code_supplier');
        });
    }
}
