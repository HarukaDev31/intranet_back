<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class InsertNewsMenuItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Primero insertar el menú padre "News"
        $newsId = DB::table('menu')->insertGetId([
            'ID_Padre' => 0,
            'Nu_Orden' => 8,
            'No_Menu' => 'Noticias',
            'No_Menu_Url' => 'noticias',
            'No_Class_Controller' => 'Noticias/NoticiasController',
            'Txt_Css_Icons' => 'fa fa-newspaper',
            'Nu_Separador' => 1,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => NULL,
            'No_Menu_China' => 'News',
            'show_father' => 0,
            'url_intranet_v2' => 'news'
        ]);

        // Luego insertar el submenú hijo
        DB::table('menu')->insert([
            [
                'ID_Padre' => $newsId,
                'Nu_Orden' => 1,
                'No_Menu' => 'Noticias',
                'No_Menu_Url' => 'noticias',
                'No_Class_Controller' => 'Noticias/NoticiasController',
                'Txt_Css_Icons' => 'fa fa-newspaper',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'Txt_Url_Video' => NULL,
                'No_Menu_China' => 'News',
                'show_father' => 0,
                'url_intranet_v2' => 'noticias'
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
        DB::table('menu')->where('No_Menu', 'News')->where('ID_Padre', '!=', 0)->delete();
        DB::table('menu')->where('No_Menu', 'News')->where('ID_Padre', '=', 0)->delete();
    }
}
