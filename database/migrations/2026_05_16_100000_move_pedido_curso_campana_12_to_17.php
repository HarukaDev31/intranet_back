<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MovePedidoCursoCampana12To17 extends Migration
{
    private const CAMPANA_ORIGEN = 12;
    private const CAMPANA_DESTINO = 17;
    private const TABLA_BACKUP = 'migration_pedido_curso_campana_12_ids';

    public function up()
    {
        if (!Schema::hasTable('pedido_curso')) {
            return;
        }

        Schema::create(self::TABLA_BACKUP, function (Blueprint $table) {
            $table->unsignedInteger('ID_Pedido_Curso')->primary();
        });

        $ids = DB::table('pedido_curso')
            ->where('ID_Campana', self::CAMPANA_ORIGEN)
            ->pluck('ID_Pedido_Curso')
            ->all();

        if (empty($ids)) {
            Schema::dropIfExists(self::TABLA_BACKUP);
            return;
        }

        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = array_map(function ($id) {
                return ['ID_Pedido_Curso' => $id];
            }, $chunk);
            DB::table(self::TABLA_BACKUP)->insert($rows);
        }

        DB::table('pedido_curso')
            ->whereIn('ID_Pedido_Curso', $ids)
            ->update(['ID_Campana' => self::CAMPANA_DESTINO]);
    }

    public function down()
    {
        if (!Schema::hasTable('pedido_curso') || !Schema::hasTable(self::TABLA_BACKUP)) {
            return;
        }

        $ids = DB::table(self::TABLA_BACKUP)->pluck('ID_Pedido_Curso')->all();

        if (!empty($ids)) {
            foreach (array_chunk($ids, 500) as $chunk) {
                DB::table('pedido_curso')
                    ->whereIn('ID_Pedido_Curso', $chunk)
                    ->where('ID_Campana', self::CAMPANA_DESTINO)
                    ->update(['ID_Campana' => self::CAMPANA_ORIGEN]);
            }
        }

        Schema::dropIfExists(self::TABLA_BACKUP);
    }
}
