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
            // Añadimos la columna fecha_documentacion_max de tipo DATE, nullable
            // y la colocamos después de fecha_levante.
            $table->date('fecha_documentacion_max')->nullable()->after('fecha_levante');
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
            if (Schema::hasColumn('carga_consolidada_contenedor', 'fecha_documentacion_max')) {
                $table->dropColumn('fecha_documentacion_max');
            }
        });
    }
};
