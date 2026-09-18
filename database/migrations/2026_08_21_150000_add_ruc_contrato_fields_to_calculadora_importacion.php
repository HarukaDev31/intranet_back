<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRucContratoFieldsToCalculadoraImportacion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->string('domicilio_fiscal', 500)->nullable()->after('razon_social');
            $table->string('coordinador_operativo_nombre', 255)->nullable()->after('domicilio_fiscal');
            $table->string('coordinador_operativo_dni', 20)->nullable()->after('coordinador_operativo_nombre');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->dropColumn([
                'domicilio_fiscal',
                'coordinador_operativo_nombre',
                'coordinador_operativo_dni',
            ]);
        });
    }
}
