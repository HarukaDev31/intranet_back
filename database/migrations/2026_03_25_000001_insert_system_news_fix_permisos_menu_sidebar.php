<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\SystemNews;

class InsertSystemNewsFixPermisosMenuSidebar extends Migration
{
    public function up()
    {
        // Obtener el primer usuario disponible para created_by
        $firstUserId = DB::table('usuario')->value('ID_Usuario');
        if (!$firstUserId) {
            $firstUserId = 1;
        }

        $now = now();

        DB::table('system_news')->insert([
            'title' => 'Corrección: permisos de menú por cargo y visualización en sidebar',
            'content' => '<p>Pedimos disculpas por las molestias ocasionadas. Se corrigió un problema donde, en algunos casos, los permisos de menú asignados a un cargo no se reflejaban correctamente para todos los usuarios y ciertos accesos no aparecían en el sidebar.</p><p>Con esta actualización, los permisos se aplican de forma consistente y el menú se muestra correctamente al ingresar.</p>',
            'summary' => 'Se corrigió un problema de permisos de menú por cargo que podía ocultar accesos en el sidebar. Disculpas por las molestias.',
            'type' => SystemNews::TYPE_FIX,
            'is_published' => true,
            'published_at' => $now->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_DOCUMENTACION,
            'redirect' => '/news',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down()
    {
        DB::table('system_news')
            ->where('title', 'Corrección: permisos de menú por cargo y visualización en sidebar')
            ->delete();
    }
}

