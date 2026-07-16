<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrigenMarketingToContenedorConsolidadoCotizacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'origen_marketing')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->string('origen_marketing', 100)->nullable()->after('id_tipo_cliente');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'origen_marketing')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->dropColumn('origen_marketing');
            });
        }
    }
}
