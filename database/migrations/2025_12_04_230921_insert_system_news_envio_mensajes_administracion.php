<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\SystemNews;

class InsertSystemNewsEnvioMensajesAdministracion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Obtener el primer usuario disponible para created_by
        $firstUserId = DB::table('usuario')->value('ID_Usuario');
        if (!$firstUserId) {
            $firstUserId = 1;
        }

        // Noticia: Envío de mensajes desde el perfil administración
        DB::table('system_news')->insert([
            'title' => 'Envío de mensajes desde el perfil administración',
            'content' => 'Se ha implementado la funcionalidad de envío de mensajes desde el perfil de administración. Ahora los usuarios con permisos de administración pueden enviar mensajes de recordatorio y cobro directamente desde la plataforma, facilitando la comunicación con los clientes durante el proceso de entrega y gestión de pagos. Esta mejora permite un mejor control y seguimiento de las comunicaciones enviadas, optimizando el flujo de trabajo del equipo administrativo.',
            'summary' => 'Nueva funcionalidad para enviar mensajes de recordatorio y cobro desde el perfil administración.',
            'type' => 'feature',
            'is_published' => true,
            'published_at' => now()->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_ADMINISTRACION,
            'redirect' => '/news',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('system_news')
            ->where('title', 'Envío de mensajes desde el perfil administración')
            ->delete();
    }
}
