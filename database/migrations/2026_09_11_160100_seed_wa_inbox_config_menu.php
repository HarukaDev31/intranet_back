<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedWaInboxConfigMenu extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('menu')) {
            return;
        }

        $orgs = DB::table('organizacion')->pluck('ID_Organizacion');
        if ($orgs->isEmpty()) {
            $orgs = collect([1]);
        }

        foreach ($orgs as $orgId) {
            $orgId = (int) $orgId;
            $this->ensureMenuForOrganizacion($orgId);
        }
    }

    public function down()
    {
        $menuIds = DB::table('menu')
            ->where('url_intranet_v2', 'admin/whatsapp')
            ->pluck('ID_Menu');
        if ($menuIds->isEmpty()) {
            return;
        }

        DB::table('menu_acceso')->whereIn('ID_Menu', $menuIds)->delete();
        DB::table('menu')->whereIn('ID_Menu', $menuIds)->delete();
    }

    /**
     * @param  int  $orgId
     * @return void
     */
    private function ensureMenuForOrganizacion($orgId)
    {
        $query = DB::table('menu')->where('url_intranet_v2', 'admin/whatsapp');
        if (Schema::hasColumn('menu', 'ID_Organizacion')) {
            $query->where('ID_Organizacion', $orgId);
        }
        $menuId = $query->value('ID_Menu');

        if (!$menuId) {
            $padreId = 0;
            $maxOrden = (int) DB::table('menu')->where('ID_Padre', $padreId)->max('Nu_Orden');
            $payload = [
                'ID_Padre' => $padreId,
                'Nu_Orden' => $maxOrden + 1,
                'No_Menu' => 'WhatsApp',
                'No_Menu_Url' => 'admin/whatsapp',
                'No_Class_Controller' => 'WhatsappInboxConfigController',
                'Txt_Css_Icons' => 'fa fa-whatsapp',
                'Nu_Separador' => 0,
                'Nu_Seguridad' => 0,
                'Nu_Activo' => 0,
                'Nu_Tipo_Sistema' => 0,
                'Txt_Url_Video' => null,
                'No_Menu_China' => 'WhatsApp',
                'show_father' => 0,
                'url_intranet_v2' => 'admin/whatsapp',
            ];
            if (Schema::hasColumn('menu', 'ID_Organizacion')) {
                $payload['ID_Organizacion'] = $orgId;
            }
            $menuId = DB::table('menu')->insertGetId($payload);
        }

        $roles = $orgId === 1
            ? ['GERENCIA', 'GERENTE GENERAL']
            : ['Socio'];

        $grupoQuery = DB::table('grupo')->whereIn('No_Grupo', $roles);
        if (Schema::hasColumn('grupo', 'ID_Organizacion')) {
            $grupoQuery->where('ID_Organizacion', $orgId);
        }
        $grupoIds = $grupoQuery->pluck('ID_Grupo');
        if ($grupoIds->isEmpty()) {
            return;
        }

        $representatives = DB::table('grupo_usuario')
            ->select('ID_Grupo', DB::raw('MIN(ID_Grupo_Usuario) as ID_Grupo_Usuario'), DB::raw('MIN(ID_Empresa) as ID_Empresa'))
            ->whereIn('ID_Grupo', $grupoIds)
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
                'ID_Empresa' => $row->ID_Empresa ?: 1,
                'ID_Menu' => $menuId,
                'ID_Grupo_Usuario' => $row->ID_Grupo_Usuario,
                'Nu_Consultar' => 1,
                'Nu_Agregar' => 1,
                'Nu_Editar' => 1,
                'Nu_Eliminar' => 0,
            ]);
        }
    }
}
