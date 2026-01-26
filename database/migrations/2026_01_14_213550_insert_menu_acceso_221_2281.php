<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class InsertMenuAcceso2212281 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Insertar acceso para el menú ID_Menu = 221 y grupo usuario ID_Grupo_Usuario = 2281
            // Insertar acceso para el menú 'cotizaciones' y grupo usuario ID_Grupo_Usuario = 2281
            $menuId = DB::table('menu')->where('url_intranet_v2', 'cotizaciones')->value('ID_Menu');
            if (! $menuId) {
                throw new \Exception('No se encontró el menú con url_intranet_v2 = cotizaciones. Asegúrate de ejecutar primero la migración que crea el menú.');
            }

            DB::table('menu_acceso')->insert([
                'ID_Empresa' => 1,
                'ID_Menu' => $menuId,
                'ID_Grupo_Usuario' => 2281,
                'Nu_Consultar' => 1,
                'Nu_Agregar' => 1,
                'Nu_Editar' => 1,
                'Nu_Eliminar' => 1
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('menu_acceso')
            ->where('ID_Menu', 221)
            ->where('ID_Grupo_Usuario', 2281)
            ->delete();
            $menuId = DB::table('menu')->where('url_intranet_v2', 'cotizaciones')->value('ID_Menu');
            if ($menuId) {
                DB::table('menu_acceso')
                    ->where('ID_Menu', $menuId)
                    ->where('ID_Grupo_Usuario', 2281)
                    ->delete();
            }
    }
}
