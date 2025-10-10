<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFromIntranetToPedidoCursoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedido_curso', function (Blueprint $table) {
            $table->tinyInteger('from_intranet')->default(0)->after('send_constancia');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedido_curso', function (Blueprint $table) {
            $table->dropColumn('from_intranet');
        });
    }
}
