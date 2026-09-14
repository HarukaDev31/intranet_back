<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('soporte_ti_mensajes', function (Blueprint $table) {
            if (!Schema::hasColumn('soporte_ti_mensajes', 'revisado')) {
                $table->boolean('revisado')->default(false)->after('es_maqueta');
            }
        });
    }

    public function down()
    {
        Schema::table('soporte_ti_mensajes', function (Blueprint $table) {
            if (Schema::hasColumn('soporte_ti_mensajes', 'revisado')) {
                $table->dropColumn('revisado');
            }
        });
    }
};
