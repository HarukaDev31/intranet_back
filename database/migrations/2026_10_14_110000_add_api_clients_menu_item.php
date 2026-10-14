<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menú "API Clientes" (Panel de Acceso, solo organización 1).
 *
 * El acceso se copia EXACTAMENTE de quienes ya ven "Organización" (hoy GERENCIA); además el
 * backend exige org 1 + GERENCIA/GERENTE GENERAL/root (ApiClientAdminController::autorizar),
 * así que otorgar el menú a otro usuario no le abre la API del mantenedor.
 */
class AddApiClientsMenuItem extends Migration
{
    private const URL = 'panel-acceso/api-clients';

    public function up()
    {
        if (!Schema::hasTable('menu') || !Schema::hasTable('menu_acceso')) {
            return;
        }

        $organizacionMenu = DB::table('menu')->where('url_intranet_v2', 'panel-acceso/organizaciones')->orderBy('ID_Menu')->first();
        if (!$organizacionMenu) {
            return;
        }

        $menuId = DB::table('menu')->where('url_intranet_v2', self::URL)->value('ID_Menu');
        if (!$menuId) {
            $menuId = DB::table('menu')->insertGetId([
                'ID_Organizacion' => $organizacionMenu->ID_Organizacion,
                'ID_Padre' => $organizacionMenu->ID_Padre,
                'Nu_Orden' => 5,
                'No_Menu' => 'API Clientes',
                'No_Menu_Url' => '',
                'No_Class_Controller' => '',
                'Txt_Css_Icons' => 'heroicons:key',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'Txt_Url_Video' => null,
                'No_Menu_China' => 'API Clientes',
                'url_intranet_v2' => self::URL,
                'show_father' => 1,
            ]);
        }

        // Mismos grupos_usuario que ya ven "Organización", con solo consulta.
        $accesos = DB::table('menu_acceso')->where('ID_Menu', $organizacionMenu->ID_Menu)->get();
        foreach ($accesos as $acceso) {
            $existe = DB::table('menu_acceso')
                ->where('ID_Menu', $menuId)
                ->where('ID_Grupo_Usuario', $acceso->ID_Grupo_Usuario)
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('menu_acceso')->insert([
                'ID_Empresa' => $acceso->ID_Empresa,
                'ID_Menu' => $menuId,
                'ID_Grupo_Usuario' => $acceso->ID_Grupo_Usuario,
                'Nu_Consultar' => 1,
                'Nu_Agregar' => 0,
                'Nu_Editar' => 0,
                'Nu_Eliminar' => 0,
            ]);
        }
    }

    public function down()
    {
        $menuId = DB::table('menu')->where('url_intranet_v2', self::URL)->value('ID_Menu');
        if ($menuId) {
            DB::table('menu_acceso')->where('ID_Menu', $menuId)->delete();
            DB::table('menu')->where('ID_Menu', $menuId)->delete();
        }
    }
}
