<?php

use App\Services\Auth\AuthMenuCacheService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** El menú «Pagos» del socio va justo después de «Consolidados» (antes de Chat y Clientes), como en la maqueta. */
return new class extends Migration
{
    public function up(): void
    {
        $pagos = DB::table('menu')->where('url_intranet_v2', 'pagos-socio')->value('ID_Menu');
        $anchor = DB::table('menu')->where('ID_Padre', 0)->whereIn('No_Menu', ['Carga Consolidada', 'Consolidados', 'Consolidado'])->max('Nu_Orden');
        if (!$pagos || $anchor === null) {
            return;
        }

        DB::table('menu')->where('ID_Padre', 0)->where('ID_Menu', '<>', $pagos)
            ->where('Nu_Orden', '>', (int) $anchor)->where('Nu_Orden', '<', 100)
            ->increment('Nu_Orden');
        DB::table('menu')->where('ID_Menu', $pagos)->update(['Nu_Orden' => (int) $anchor + 1]);

        if (class_exists(AuthMenuCacheService::class)) {
            app(AuthMenuCacheService::class)->invalidateAll();
        }
    }

    public function down(): void
    {
    }
};
