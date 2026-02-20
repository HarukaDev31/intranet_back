<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class InsertMenuAbiertosCargaConsolidada extends Migration
{
    /**
     * Run the migrations.
     * Inserta ítem de menú "Abiertos" (Carga Consolidada) bajo el padre 184.
     *
     * @return void
     */
    public function up()
    {
        DB::table('menu')->insert([
            'ID_Padre'            => 184,
            'Nu_Orden'            => 5,
            'No_Menu'             => 'Abiertos',
            'No_Menu_Url'         => 'CargaConsolidada/ContenedorConsolidado/listarCompletados',
            'No_Class_Controller' => 'ContenedorConsolidado',
            'Txt_Css_Icons'       => '',
            'Nu_Separador'        => 1,
            'Nu_Seguridad'        => 0,
            'Nu_Activo'           => 0,
            'Nu_Tipo_Sistema'     => 0,
            'Txt_Url_Video'       => null,
            'No_Menu_China'       => 'Consolidated Cargo',
            'url_intranet_v2'     => 'cargaconsolidada/abiertos',
            'show_father'         => 1,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('menu')->where('ID_Menu', 193)->delete();
    }
}
