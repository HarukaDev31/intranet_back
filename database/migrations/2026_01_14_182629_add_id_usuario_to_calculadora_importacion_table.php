<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdUsuarioToCalculadoraImportacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            if (!Schema::hasColumn('calculadora_importacion', 'id_usuario')) {
                $table->unsignedInteger('id_usuario')->nullable()->after('id_cliente');
            }
        });
        
        // Agregar la clave foránea en una operación separada
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->foreign('id_usuario')->references('ID_Usuario')->on('usuario')->onDelete('set null');
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
            $table->dropForeign(['id_usuario']);
            $table->dropColumn('id_usuario');
        });
    }
}
