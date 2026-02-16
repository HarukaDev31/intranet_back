<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class InsertPermisosMenuInAduanas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $aduanas = DB::table('menu')->where('No_Menu', 'Aduanas')->first();

        if (!$aduanas) {
            return;
        }

        $padreId = $aduanas->ID_Menu;

        // Obtener el máximo orden actual de los hijos de Aduanas
        $maxOrden = DB::table('menu')->where('ID_Padre', $padreId)->max('Nu_Orden');
        $nuevoOrden = ($maxOrden !== null) ? $maxOrden + 1 : 1;

        DB::table('menu')->insert([
            'ID_Padre' => $padreId,
            'Nu_Orden' => $nuevoOrden,
            'No_Menu' => 'Permisos',
            'No_Menu_Url' => 'Aduanas/PermisosController/index',
            'No_Class_Controller' => 'Aduanas/PermisosController',
            'Txt_Css_Icons' => 'fa fa-id-card',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => NULL,
            'No_Menu_China' => NULL,
            'show_father' => 1,
            'url_intranet_v2' => 'basedatos/permisos',
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('menu')->where('No_Menu', 'Permisos')->where('url_intranet_v2', 'basedatos/permisos')->delete();
    }
}
