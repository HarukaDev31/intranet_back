<?php

use App\Services\Auth\AuthMenuCacheService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Encargado China (Armando): menú según el diseño → Inicio, Carga consolidada, Flujo de caja (lo agrega el front con Operaciones),
 * Operaciones, Pago a proveedores y Clientes. Sin Customers ni Manual de usuario. Reafirma el rol de armando@probusiness.pe.
 */
return new class extends Migration
{
    private const ROL = 'Encargado China';
    private const ALMACEN = 'ContenedorAlmacen';
    private const USUARIO = 'armando@probusiness.pe';

    public function up(): void
    {
        $grupoIds = DB::table('grupo')->where('No_Grupo', self::ROL)->pluck('ID_Grupo')->all();
        if ($grupoIds === []) {
            return;
        }

        // Reafirma el rol de Armando (por si la migración anterior corrió antes de que existiera el usuario).
        $usuario = DB::table('usuario')->where('No_Usuario', self::USUARIO)->first();
        if ($usuario) {
            $destinoId = (int) $grupoIds[0];
            DB::table('usuario')->where('ID_Usuario', $usuario->ID_Usuario)->update(['ID_Grupo' => $destinoId]);
            DB::table('grupo_usuario')
                ->where('ID_Usuario', $usuario->ID_Usuario)
                ->whereIn('ID_Grupo', DB::table('grupo')->where('No_Grupo', self::ALMACEN)->pluck('ID_Grupo'))
                ->update(['ID_Grupo' => $destinoId]);
        }

        $quitar = DB::table('menu')
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('No_Menu', 'Customers')->where('ID_Padre', 0))->orWhere('url_intranet_v2', 'manual-usuario'))
            ->pluck('ID_Menu')
            ->all();
        $miembros = DB::table('grupo_usuario')->whereIn('ID_Grupo', $grupoIds)->pluck('ID_Grupo_Usuario')->all();
        if ($quitar !== [] && $miembros !== []) {
            DB::table('menu_acceso')->whereIn('ID_Menu', $quitar)->whereIn('ID_Grupo_Usuario', $miembros)->delete();
        }

        if (class_exists(AuthMenuCacheService::class)) {
            app(AuthMenuCacheService::class)->invalidateAll();
        }
    }

    public function down(): void
    {
    }
};
