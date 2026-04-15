<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPesoChinaToContenedorConsolidadoCotizacionProveedores extends Migration
{
    private $table = 'contenedor_consolidado_cotizacion_proveedores';
    private $column = 'peso_china';

    public function up()
    {
        if (!Schema::hasTable($this->table) || Schema::hasColumn($this->table, $this->column)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            $table->decimal('peso_china', 12, 2)->default(0)->after('peso');
        });
    }

    public function down()
    {
        if (!Schema::hasTable($this->table) || !Schema::hasColumn($this->table, $this->column)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            $table->dropColumn($this->column);
        });
    }
}
