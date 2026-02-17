<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class InsertDocumentacionCoordinacionMenusInCargaConsolidada extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //WHERE ID_Menu = 158
        $cargaConsolidada = DB::table('menu')->where('ID_Menu', 184)->first();

        if (!$cargaConsolidada) {
            return;
        }

        $padreId = $cargaConsolidada->ID_Menu;
        $maxOrden = DB::table('menu')->where('ID_Padre', $padreId)->max('Nu_Orden');
        $orden = ($maxOrden !== null) ? $maxOrden + 1 : 1;

        // Documentación (hijo de Carga Consolidada)
        $documentacionId = DB::table('menu')->insertGetId([
            'ID_Padre' => $padreId,
            'Nu_Orden' => $orden,
            'No_Menu' => 'Documentación',
            'No_Menu_Url' => '#',
            'No_Class_Controller' => '',
            'Txt_Css_Icons' => 'fa fa-file-text',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => NULL,
            'No_Menu_China' => NULL,
            'show_father' => 1,
            'url_intranet_v2' => NULL,
        ]);

        // Documentación > Abiertos
        DB::table('menu')->insert([
            'ID_Padre' => $documentacionId,
            'Nu_Orden' => 1,
            'No_Menu' => 'Abiertos',
            'No_Menu_Url' => 'CargaConsolidada/DocumentacionController/abiertos',
            'No_Class_Controller' => 'CargaConsolidada/DocumentacionController',
            'Txt_Css_Icons' => 'fa fa-folder-open',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => NULL,
            'No_Menu_China' => NULL,
            'show_father' => 1,
            'url_intranet_v2' => 'carga-consolidada/documentacion/abiertos',
        ]);

        // Documentación > Completados
        DB::table('menu')->insert([
            'ID_Padre' => $documentacionId,
            'Nu_Orden' => 2,
            'No_Menu' => 'Completados',
            'No_Menu_Url' => 'CargaConsolidada/DocumentacionController/completados',
            'No_Class_Controller' => 'CargaConsolidada/DocumentacionController',
            'Txt_Css_Icons' => 'fa fa-check-circle',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => NULL,
            'No_Menu_China' => NULL,
            'show_father' => 1,
            'url_intranet_v2' => 'carga-consolidada/documentacion/completados',
        ]);

        // Coordinación (hijo de Carga Consolidada)
        $coordinacionId = DB::table('menu')->insertGetId([
            'ID_Padre' => $padreId,
            'Nu_Orden' => $orden + 1,
            'No_Menu' => 'Coordinación',
            'No_Menu_Url' => '#',
            'No_Class_Controller' => '',
            'Txt_Css_Icons' => 'fa fa-users',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => NULL,
            'No_Menu_China' => NULL,
            'show_father' => 1,
            'url_intranet_v2' => NULL,
        ]);

        // Coordinación > Abiertos
        DB::table('menu')->insert([
            'ID_Padre' => $coordinacionId,
            'Nu_Orden' => 1,
            'No_Menu' => 'Abiertos',
            'No_Menu_Url' => 'CargaConsolidada/CoordinacionController/abiertos',
            'No_Class_Controller' => 'CargaConsolidada/CoordinacionController',
            'Txt_Css_Icons' => 'fa fa-folder-open',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => NULL,
            'No_Menu_China' => NULL,
            'show_father' => 1,
            'url_intranet_v2' => 'carga-consolidada/coordinacion/abiertos',
        ]);

        // Coordinación > Completados
        DB::table('menu')->insert([
            'ID_Padre' => $coordinacionId,
            'Nu_Orden' => 2,
            'No_Menu' => 'Completados',
            'No_Menu_Url' => 'CargaConsolidada/CoordinacionController/completados',
            'No_Class_Controller' => 'CargaConsolidada/CoordinacionController',
            'Txt_Css_Icons' => 'fa fa-check-circle',
            'Nu_Separador' => 0,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => NULL,
            'No_Menu_China' => NULL,
            'show_father' => 1,
            'url_intranet_v2' => 'carga-consolidada/coordinacion/completados',
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar sub-hijos por url_intranet_v2
        DB::table('menu')->where('url_intranet_v2', 'carga-consolidada/documentacion/abiertos')->delete();
        DB::table('menu')->where('url_intranet_v2', 'carga-consolidada/documentacion/completados')->delete();
        DB::table('menu')->where('url_intranet_v2', 'carga-consolidada/coordinacion/abiertos')->delete();
        DB::table('menu')->where('url_intranet_v2', 'carga-consolidada/coordinacion/completados')->delete();

        // Eliminar padres
        $cargaConsolidada = DB::table('menu')->where('No_Menu', 'Carga Consolidada')->first();
        if ($cargaConsolidada) {
            DB::table('menu')->where('ID_Padre', $cargaConsolidada->ID_Menu)
                ->where('No_Menu', 'Documentación')
                ->where('No_Menu_Url', '#')
                ->delete();
            DB::table('menu')->where('ID_Padre', $cargaConsolidada->ID_Menu)
                ->where('No_Menu', 'Coordinación')
                ->where('No_Menu_Url', '#')
                ->delete();
        }
    }
}
