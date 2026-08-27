<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('soporte_ti_mensajes', function (Blueprint $table) {
            if (!Schema::hasColumn('soporte_ti_mensajes', 'es_maqueta')) {
                $table->boolean('es_maqueta')->default(false)->after('es_sistema');
            }
        });
    }

    public function down()
    {
        Schema::table('soporte_ti_mensajes', function (Blueprint $table) {
            if (Schema::hasColumn('soporte_ti_mensajes', 'es_maqueta')) {
                $table->dropColumn('es_maqueta');
            }
        });
    }
};
