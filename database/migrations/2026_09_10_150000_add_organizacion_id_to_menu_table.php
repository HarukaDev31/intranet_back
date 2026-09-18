<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El catalogo de menus era global (sin organizacion): cualquier org veia y
 * podia asignar cualquier menu. Se agrega ID_Organizacion para que cada
 * organizacion tenga su propio catalogo, igual que grupo/usuario.
 */
class AddOrganizacionIdToMenuTable extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('menu', 'ID_Organizacion')) {
            return;
        }

        Schema::table('menu', function (Blueprint $table) {
            $table->unsignedInteger('ID_Organizacion')->nullable()->after('ID_Menu');
        });

        // Toda la data historica pertenece a la unica organizacion existente hoy.
        $organizacionActual = DB::table('organizacion')->orderBy('ID_Organizacion')->value('ID_Organizacion');
        if ($organizacionActual !== null) {
            DB::table('menu')->whereNull('ID_Organizacion')->update(['ID_Organizacion' => $organizacionActual]);
        }

        Schema::table('menu', function (Blueprint $table) {
            $table->index('ID_Organizacion', 'idx_menu_organizacion_id');
            $table->foreign('ID_Organizacion', 'fk_menu_organizacion_id')
                ->references('ID_Organizacion')
                ->on('organizacion')
                ->onDelete('restrict');
        });
    }

    public function down()
    {
        if (!Schema::hasColumn('menu', 'ID_Organizacion')) {
            return;
        }

        Schema::table('menu', function (Blueprint $table) {
            $table->dropForeign('fk_menu_organizacion_id');
            $table->dropIndex('idx_menu_organizacion_id');
            $table->dropColumn('ID_Organizacion');
        });
    }
}
