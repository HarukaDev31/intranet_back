<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class InsertBaseDatosMenuItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Primero insertar el menú padre "Base de Datos"
        $baseDatosId = DB::table('menu')->insertGetId([
            'ID_Padre' => 0,
            'Nu_Orden' => 7,
            'No_Menu' => 'Base de Datos',
            'No_Menu_Url' => '#',
            'No_Class_Controller' => 'BaseDatos/ProductosController',
            'Txt_Css_Icons' => 'fa fa-list',
            'Nu_Separador' => 1,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => NULL,
            'No_Menu_China' => 'Orders',
            'show_father' => 1,
            'url_intranet_v2' => NULL
        ]);

        // Luego insertar los submenús
        DB::table('menu')->insert([
            [
                'ID_Padre' => $baseDatosId,
                'Nu_Orden' => 1,
                'No_Menu' => 'Productos',
                'No_Menu_Url' => 'BaseDatos/ProductosController/index',
                'No_Class_Controller' => 'BaseDatos/ProductosController',
                'Txt_Css_Icons' => 'fa fa-file-excel',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'Txt_Url_Video' => NULL,
                'No_Menu_China' => 'Orders',
                'show_father' => 1,
                'url_intranet_v2' => 'basedatos/productos'
            ],
            [
                'ID_Padre' => $baseDatosId,
                'Nu_Orden' => 2,
                'No_Menu' => 'Regulaciones',
                'No_Menu_Url' => 'BaseDatos/RegulacionesController/index',
                'No_Class_Controller' => 'BaseDatos/RegulacionesController',
                'Txt_Css_Icons' => 'fa fa-file-excel',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'Txt_Url_Video' => NULL,
                'No_Menu_China' => 'Orders',
                'show_father' => 1,
                'url_intranet_v2' => 'basedatos/regulaciones'
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar los registros de menú insertados
        DB::table('menu')->where('No_Menu', 'Regulaciones')->delete();
        DB::table('menu')->where('No_Menu', 'Productos')->delete();
        DB::table('menu')->where('No_Menu', 'Base de Datos')->delete();
    }
}
