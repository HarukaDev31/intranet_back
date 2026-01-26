<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAutosignedContractFieldsToContenedorConsolidadoCotizacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->datetime('autosigned_contract_at')->nullable()->after('cotizacion_contrato_firmado_url');
            $table->text('cotizacion_contrato_autosigned_url')->nullable()->after('autosigned_contract_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->dropColumn(['autosigned_contract_at', 'cotizacion_contrato_autosigned_url']);
        });
    }
}
