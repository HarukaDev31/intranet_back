<?php

use App\Support\Register\ComoEnteroCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $comment = ComoEnteroCatalog::columnComment();
        $pdo = DB::getPdo();
        $quoted = $pdo->quote($comment);

        // MODIFY en tablas legacy puede fallar por fechas 0000-00-00 en modo estricto.
        $previousMode = DB::selectOne('SELECT @@SESSION.sql_mode AS m');
        DB::statement("SET SESSION sql_mode = ''");

        try {
            if (Schema::hasTable('entidad') && Schema::hasColumn('entidad', 'Nu_Como_Entero_Empresa')) {
                DB::statement(
                    "ALTER TABLE `entidad` MODIFY `Nu_Como_Entero_Empresa` INT NULL COMMENT {$quoted}"
                );
            }

            if (Schema::hasTable('users') && Schema::hasColumn('users', 'no_como_entero')) {
                DB::statement(
                    "ALTER TABLE `users` MODIFY `no_como_entero` INT NULL COMMENT {$quoted}"
                );
            }
        } finally {
            $mode = $previousMode->m ?? '';
            DB::statement('SET SESSION sql_mode = ' . $pdo->quote($mode));
        }
    }

    public function down()
    {
        $legacy = '1=Tiktok, 2=Facebook, 3=Instagram, 4=Youtube, 5=Familiares/Amigos, 6=LinkedIn, 7=Google, 8=Otros';
        $pdo = DB::getPdo();
        $quoted = $pdo->quote($legacy);

        $previousMode = DB::selectOne('SELECT @@SESSION.sql_mode AS m');
        DB::statement("SET SESSION sql_mode = ''");

        try {
            if (Schema::hasTable('entidad') && Schema::hasColumn('entidad', 'Nu_Como_Entero_Empresa')) {
                DB::statement(
                    "ALTER TABLE `entidad` MODIFY `Nu_Como_Entero_Empresa` INT NULL COMMENT {$quoted}"
                );
            }

            if (Schema::hasTable('users') && Schema::hasColumn('users', 'no_como_entero')) {
                DB::statement(
                    "ALTER TABLE `users` MODIFY `no_como_entero` INT NULL COMMENT ''"
                );
            }
        } finally {
            $mode = $previousMode->m ?? '';
            DB::statement('SET SESSION sql_mode = ' . $pdo->quote($mode));
        }
    }
};
