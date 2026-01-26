<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class SoftDeleteManualTipoCliente extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Soft delete los registros con tipo MANUAL
        DB::table('calculadora_tipo_cliente')
            ->where('nombre', 'MANUAL')
            ->update(['deleted_at' => now()]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Restaurar los registros con tipo MANUAL (eliminar soft delete)
        DB::table('calculadora_tipo_cliente')
            ->where('nombre', 'MANUAL')
            ->update(['deleted_at' => null]);
    }
}
