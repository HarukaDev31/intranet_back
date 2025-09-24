<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class InsertSampleMenuUserData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        

        $importacionesId = DB::table('menu_user')->insertGetId([
            'ID_Padre' => 0,
            'Nu_Orden' => 2,
            'No_Menu' => 'Mis importaciones',
            'No_Menu_Url' => '#',
            'No_Class_Controller' => '',
            'Txt_Css_Icons' => 'fas fa-calculator',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => null,
            'No_Menu_China' => null,
            'url_intranet_v2' => 'importaciones',
            'show_father' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $trayecto=DB::table('menu_user')->insertGetId([
            'ID_Padre' => $importacionesId,
            'Nu_Orden' => 1,
            'No_Menu' => 'Trayectos',
            'No_Menu_Url' => '#',
            'No_Class_Controller' => '',
            'Txt_Css_Icons' => 'fas fa-calculator',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => null,
            'No_Menu_China' => null,
            'url_intranet_v2' => 'importaciones/trayectos',
            'show_father' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $entregadosId = DB::table('menu_user')->insertGetId([
            'ID_Padre' => $importacionesId,
            'Nu_Orden' => 2,
            'No_Menu' => 'Entregados',
            'No_Menu_Url' => '#',
            'No_Class_Controller' => '',
            'Txt_Css_Icons' => 'fas fa-calculator',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => null,
            'No_Menu_China' => null,
            'url_intranet_v2' => 'importaciones/entregados',
            'show_father' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
