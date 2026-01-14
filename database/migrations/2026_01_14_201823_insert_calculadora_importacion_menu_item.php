<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class InsertCalculadoraImportacionMenuItem extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Insertar el menú de Calculadora de Importación
        DB::table('menu')->insert([
            'ID_Padre' => 0,
            'Nu_Orden' => 2, // Ajusta según dónde quieras que aparezca
            'No_Menu' => 'Cotizador',
            'No_Menu_Url' => 'cotizaciones',
            'No_Class_Controller' => 'CalculadoraImportacionController',
            'Txt_Css_Icons' => 'fa fa-calculator',
            'Nu_Separador' => 1,
            'Nu_Seguridad' => 0,
            'Nu_Activo' => 0,
            'Nu_Tipo_Sistema' => 0,
            'Txt_Url_Video' => NULL,
            'No_Menu_China' => 'Import Calculator',
            'show_father' => 0,
            'url_intranet_v2' => 'cotizaciones'
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('menu')->where('No_Menu', 'Calculadora Importación')->delete();
    }
}
