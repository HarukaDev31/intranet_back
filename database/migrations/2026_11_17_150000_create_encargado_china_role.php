<?php

use App\Services\Auth\AuthMenuCacheService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rol «Encargado China»: lleva Operaciones, Clientes (China), Flujo de caja y Pago a Proveedor (China).
 * El almacén de Carga Consolidada (ContenedorAlmacen) ya no ve esos módulos. Armando pasa al nuevo rol.
 */
return new class extends Migration
{
    private const ROL = 'Encargado China';
    private const ALMACEN = 'ContenedorAlmacen';
    private const USUARIO = 'armando@probusiness.pe';

    public function up(): void
    {
        $origen = DB::table('grupo')->where('No_Grupo', self::ALMACEN)->orderBy('ID_Grupo')->first();
        if (!$origen) {
            return;
        }

        $destinoId = DB::table('grupo')->where('No_Grupo', self::ROL)->where('ID_Organizacion', $origen->ID_Organizacion)->value('ID_Grupo');
        if (!$destinoId) {
            $fila = (array) $origen;
            unset($fila['ID_Grupo']);
            $fila['No_Grupo'] = self::ROL;
            $destinoId = DB::table('grupo')->insertGetId($fila);
        }

        $usuario = DB::table('usuario')->where('No_Usuario', self::USUARIO)->first();
        if ($usuario) {
            DB::table('usuario')->where('ID_Usuario', $usuario->ID_Usuario)->update(['ID_Grupo' => $destinoId]);
            DB::table('grupo_usuario')
                ->where('ID_Usuario', $usuario->ID_Usuario)
                ->whereIn('ID_Grupo', DB::table('grupo')->where('No_Grupo', self::ALMACEN)->pluck('ID_Grupo'))
                ->update(['ID_Grupo' => $destinoId]);
        }

        // Los demás miembros del almacén dejan de ver los módulos de China.
        $padreId = DB::table('menu')->where('No_Menu', 'Pago a Proveedor')->where('ID_Padre', 0)->value('ID_Menu');
        $menus = DB::table('menu')->whereIn('url_intranet_v2', ['operaciones-china', 'clientes-china'])->pluck('ID_Menu')->all();
        if ($padreId) {
            $menus[] = (int) $padreId;
            $menus = array_merge($menus, DB::table('menu')->where('ID_Padre', $padreId)->pluck('ID_Menu')->all());
        }
        $miembrosAlmacen = DB::table('grupo_usuario as gu')
            ->join('grupo as g', 'g.ID_Grupo', '=', 'gu.ID_Grupo')
            ->where('g.No_Grupo', self::ALMACEN)
            ->pluck('gu.ID_Grupo_Usuario');
        if ($menus !== [] && $miembrosAlmacen->isNotEmpty()) {
            DB::table('menu_acceso')->whereIn('ID_Menu', $menus)->whereIn('ID_Grupo_Usuario', $miembrosAlmacen)->delete();
        }

        if (class_exists(AuthMenuCacheService::class)) {
            app(AuthMenuCacheService::class)->invalidateAll();
        }
    }

    public function down(): void
    {
    }
};
