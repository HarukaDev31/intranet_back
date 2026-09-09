<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class InsertManualUsuarioMenuItem extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $exists = DB::table('menu')
            ->where('url_intranet_v2', 'manual-usuario')
            ->orWhere('No_Menu', 'Manual de usuario')
            ->exists();

        if ($exists) {
            $menuId = DB::table('menu')
                ->where('url_intranet_v2', 'manual-usuario')
                ->value('ID_Menu');
        } else {
            $maxOrden = (int) DB::table('menu')->where('ID_Padre', 0)->max('Nu_Orden');

            $menuId = DB::table('menu')->insertGetId([
                'ID_Padre' => 0,
                'Nu_Orden' => $maxOrden + 1,
                'No_Menu' => 'Manual de usuario',
                'No_Menu_Url' => 'manual-usuario',
                'No_Class_Controller' => 'ManualUsuarioController',
                'Txt_Css_Icons' => 'fa fa-book',
                'Nu_Separador' => 1,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'Txt_Url_Video' => null,
                'No_Menu_China' => 'User Manual',
                'show_father' => 0,
                'url_intranet_v2' => 'manual-usuario',
            ]);
        }

        if (!$menuId) {
            throw new \RuntimeException('No se pudo crear/encontrar el menú Manual de usuario.');
        }

        $excluded = [1205]; // Cliente externo

        // Un ID_Grupo_Usuario representativo por ID_Grupo (basta para que el grupo vea el menú)
        $representatives = DB::table('grupo_usuario')
            ->select('ID_Grupo', DB::raw('MIN(ID_Grupo_Usuario) as ID_Grupo_Usuario'))
            ->whereNotIn('ID_Grupo', $excluded)
            ->groupBy('ID_Grupo')
            ->get();

        foreach ($representatives as $row) {
            $already = DB::table('menu_acceso')
                ->where('ID_Menu', $menuId)
                ->where('ID_Grupo_Usuario', $row->ID_Grupo_Usuario)
                ->exists();

            if ($already) {
                continue;
            }

            DB::table('menu_acceso')->insert([
                'ID_Empresa' => 1,
                'ID_Menu' => $menuId,
                'ID_Grupo_Usuario' => $row->ID_Grupo_Usuario,
                'Nu_Consultar' => 1,
                'Nu_Agregar' => 0,
                'Nu_Editar' => 0,
                'Nu_Eliminar' => 0,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        $menuId = DB::table('menu')
            ->where('url_intranet_v2', 'manual-usuario')
            ->value('ID_Menu');

        if ($menuId) {
            DB::table('menu_acceso')->where('ID_Menu', $menuId)->delete();
            DB::table('menu')->where('ID_Menu', $menuId)->delete();
        }
    }
}
