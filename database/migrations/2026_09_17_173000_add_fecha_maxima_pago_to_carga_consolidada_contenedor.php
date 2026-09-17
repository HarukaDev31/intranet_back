<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            if (!Schema::hasColumn('carga_consolidada_contenedor', 'fecha_maxima_pago')) {
                $table->date('fecha_maxima_pago')->nullable()->after('fecha_documentacion_max');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
            if (Schema::hasColumn('carga_consolidada_contenedor', 'fecha_maxima_pago')) {
                $table->dropColumn('fecha_maxima_pago');
            }
        });
    }
};
