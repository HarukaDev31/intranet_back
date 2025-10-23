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
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'cotizacion_contrato_url')) {
                $table->string('cotizacion_contrato_url')->nullable()->after('cotizacion_file_url')->comment('Relative storage path for the contract PDF (eg: contratos/filename.pdf)');
            }
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'cotizacion_contrato_firmado_url')) {
                $table->string('cotizacion_contrato_firmado_url')->nullable()->after('cotizacion_contrato_url')->comment('Relative storage path for the signed contract (eg: contratos/filename_signed.pdf)');
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
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'cotizacion_contrato_firmado_url')) {
                $table->dropColumn('cotizacion_contrato_firmado_url');
            }
            if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'cotizacion_contrato_url')) {
                $table->dropColumn('cotizacion_contrato_url');
            }
        });
    }
};
