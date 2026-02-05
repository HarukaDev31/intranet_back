<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InsertMenuProgreso extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        // Insertar el primer registro (padre) y obtener su ID_Menu autogenerado
        $padreId = DB::table('menu')->insertGetId([
            'ID_Padre' => 0,
            'Nu_Orden' => 1,
            'No_Menu' => 'Mi Progreso (Importaciones)',
            'No_Menu_Url' => 'Administracion/MiProgresoController/index',
            'No_Class_Controller' => '',
            'Txt_Css_Icons' => 'fa fa-folder',
            'Nu_Separador' => 1,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => null,
            'No_Menu_China' => null,
            'url_intranet_v2' => 'calendar',
            'show_father' => 0
        ]);

        // Insertar el segundo registro (hijo) usando el ID_Menu del padre como ID_Padre
        DB::table('menu')->insert([
            'ID_Padre' => $padreId, // Usa el ID_Menu autogenerado del registro anterior
            'Nu_Orden' => 2,
            'No_Menu' => 'Mi Progreso',
            'No_Menu_Url' => 'Administracion/MiProgresoController/index',
            'No_Class_Controller' => '',
            'Txt_Css_Icons' => 'fa fa-file',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => null,
            'No_Menu_China' => null,
            'url_intranet_v2' => null,
            'show_father' => 0
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
