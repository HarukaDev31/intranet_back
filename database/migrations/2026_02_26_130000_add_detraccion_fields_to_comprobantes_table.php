<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDetraccionFieldsToComprobantesTable extends Migration
{
    public function up()
    {
        Schema::table('contenedor_consolidado_comprobantes', function (Blueprint $table) {
            $table->tinyInteger('tiene_detraccion')->default(0)->after('valor_comprobante');
            $table->decimal('monto_detraccion_dolares', 15, 2)->nullable()->after('tiene_detraccion');
            $table->decimal('monto_detraccion_soles', 15, 2)->nullable()->after('monto_detraccion_dolares');
        });
    }

    public function down()
    {
        Schema::table('contenedor_consolidado_comprobantes', function (Blueprint $table) {
            $table->dropColumn(['tiene_detraccion', 'monto_detraccion_dolares', 'monto_detraccion_soles']);
        });
    }
}
