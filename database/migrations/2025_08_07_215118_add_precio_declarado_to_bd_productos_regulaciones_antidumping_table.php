<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPrecioDeclaradoToBdProductosRegulacionesAntidumpingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bd_productos_regulaciones_antidumping', function (Blueprint $table) {
            $table->decimal('precio_declarado', 10, 2)->nullable()->after('partida');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bd_productos_regulaciones_antidumping', function (Blueprint $table) {
            $table->dropColumn('precio_declarado');
        });
    }
}
