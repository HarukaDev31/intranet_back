<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUpdatedFromToUsuarioDatosFacturacionTable extends Migration
{
    protected $table = 'usuario_datos_facturacion';

    public function up()
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        if (!Schema::hasColumn($this->table, 'updated_from')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->string('updated_from', 64)->nullable()->after('domicilio_fiscal');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        if (Schema::hasColumn($this->table, 'updated_from')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropColumn('updated_from');
            });
        }
    }
}
