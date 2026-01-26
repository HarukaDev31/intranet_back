<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNoComoEnteroAndNoOtrosComoEnteroEmpresaToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('no_como_entero')->nullable()->after('idcity');
            $table->string('no_otros_como_entero_empresa')->nullable()->after('no_como_entero');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['no_como_entero', 'no_otros_como_entero_empresa']);
        });
    }
}
