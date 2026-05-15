<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SoftDeleteCampanaCurso12 extends Migration
{
    private const CAMPANA_ID = 12;

    public function up()
    {
        if (!Schema::hasTable('campana_curso')) {
            return;
        }

        $pedidosAsociados = Schema::hasTable('pedido_curso')
            ? DB::table('pedido_curso')->where('ID_Campana', self::CAMPANA_ID)->count()
            : 0;

        if ($pedidosAsociados > 0) {
            throw new \RuntimeException(
                'No se puede borrar la campaña 12: aún hay ' . $pedidosAsociados . ' pedido(s) en pedido_curso con ID_Campana = 12.'
            );
        }

        if (Schema::hasTable('campana_curso_dias')) {
            DB::table('campana_curso_dias')->where('id_campana', self::CAMPANA_ID)->delete();
        }

        DB::table('campana_curso')
            ->where('ID_Campana', self::CAMPANA_ID)
            ->update(['Fe_Borrado' => now()]);
    }

    public function down()
    {
        if (!Schema::hasTable('campana_curso')) {
            return;
        }

        DB::table('campana_curso')
            ->where('ID_Campana', self::CAMPANA_ID)
            ->update(['Fe_Borrado' => null]);
    }
}
