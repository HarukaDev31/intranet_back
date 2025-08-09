<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveUniqueKeyNombreFromBdProductosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bd_productos', function (Blueprint $table) {
            // Eliminar la restricción unique del campo nombre
            $table->dropUnique('uk_nombre');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bd_productos', function (Blueprint $table) {
            // Restaurar la restricción unique del campo nombre si se necesita hacer rollback
            $table->unique('nombre', 'uk_nombre');
        });
    }
}
