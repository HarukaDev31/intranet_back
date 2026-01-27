<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class InsertViaticosReintegrosMenuItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Insertar el menú principal "Viáticos y Reintegros"
        $menuPrincipalId = DB::table('menu')->insertGetId([
            'ID_Padre' => 0,
            'Nu_Orden' => 10, // Ajusta según dónde quieras que aparezca
            'No_Menu' => 'Viáticos y Reintegros',
            'No_Menu_Url' => 'viaticos',
            'No_Class_Controller' => 'ViaticoController',
            'Txt_Css_Icons' => 'fa fa-money',
            'Nu_Separador' => 1,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => NULL,
            'No_Menu_China' => 'Travel Expenses',
            'show_father' => 1,
            'url_intranet_v2' => '#',
        ]);

        // Insertar "Mis Reintegros" como hijo directo del menú principal
        DB::table('menu')->insert([
            'ID_Padre' => $menuPrincipalId,
            'Nu_Orden' => 1,
            'No_Menu' => 'Mis Reintegros',
            'No_Menu_Url' => 'viaticos',
            'No_Class_Controller' => 'ViaticoController',
            'Txt_Css_Icons' => 'fa fa-user',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => NULL,
            'No_Menu_China' => 'My Reimbursements',
            'show_father' => 0,
            'url_intranet_v2' => 'viaticos'
        ]);

        // Insertar "Viáticos y Reintegros" como hijo del menú principal (será padre de Pendientes y Completados)
        

        // Insertar "Pendientes" como hijo de "Viáticos y Reintegros"
        DB::table('menu')->insert([
            'ID_Padre' => $menuPrincipalId,
            'Nu_Orden' => 1,
            'No_Menu' => 'Pendientes',
            'No_Menu_Url' => 'viaticos/pendientes',
            'No_Class_Controller' => 'ViaticoController',
            'Txt_Css_Icons' => 'fa fa-clock-o',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => NULL,
            'No_Menu_China' => 'Pending',
            'show_father' => 1,
            'url_intranet_v2' => 'viaticos/pendientes'
        ]);

        // Insertar "Completados" como hijo de "Viáticos y Reintegros"
        DB::table('menu')->insert([
            'ID_Padre' => $menuPrincipalId,
            'Nu_Orden' => 2,
            'No_Menu' => 'Completados',
            'No_Menu_Url' => 'viaticos/completados',
            'No_Class_Controller' => 'ViaticoController',
            'Txt_Css_Icons' => 'fa fa-check',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => NULL,
            'No_Menu_China' => 'Completed',
            'show_father' => 1,
            'url_intranet_v2' => 'viaticos/completados'
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar todos los menús relacionados con Viáticos y Reintegros
        DB::table('menu')->where('No_Menu', 'Viáticos y Reintegros')->delete();
        DB::table('menu')->where('No_Menu', 'Mis Reintegros')->delete();
        DB::table('menu')->where('No_Menu', 'Pendientes')
            ->where('No_Menu_Url', 'viaticos/pendientes')
            ->delete();
        DB::table('menu')->where('No_Menu', 'Completados')
            ->where('No_Menu_Url', 'viaticos/completados')
            ->delete();
    }
}
