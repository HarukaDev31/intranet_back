<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRucEmpresaFieldsToCalculadoraImportacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            // Hacer dni_cliente nullable ya que ahora puede ser DNI o RUC
            $table->string('dni_cliente')->nullable()->change();
            
            // Agregar campos para RUC y empresa
            $table->string('ruc_cliente')->nullable()->after('dni_cliente');
            $table->string('razon_social')->nullable()->after('ruc_cliente');
            
            // Agregar tipo de documento (DNI o RUC)
            $table->enum('tipo_documento', ['DNI', 'RUC'])->default('DNI')->after('nombre_cliente');
            
            // Agregar índice para búsqueda por RUC
            $table->index('ruc_cliente');
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
            $table->dropIndex(['ruc_cliente']);
            $table->dropColumn(['ruc_cliente', 'razon_social', 'tipo_documento']);
            $table->string('dni_cliente')->nullable(false)->change();
        });
    }
}
