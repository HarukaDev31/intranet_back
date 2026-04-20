<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdImportToUsuarioDatosFacturacionTable extends Migration
{
    protected $table = 'usuario_datos_facturacion';

    public function up()
    {
        if (Schema::hasTable($this->table) && !Schema::hasColumn($this->table, 'id_import')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->unsignedBigInteger('id_import')->nullable()->after('id_user');
                $table->index('id_import', 'idx_udf_id_import');

                $table->foreign('id_import', 'fk_udf_id_import')
                    ->references('id')
                    ->on('imports_usuario_datos_facturacion')
                    ->onDelete('set null');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable($this->table) && Schema::hasColumn($this->table, 'id_import')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropForeign('fk_udf_id_import');
                $table->dropIndex('idx_udf_id_import');
                $table->dropColumn('id_import');
            });
        }
    }
}

