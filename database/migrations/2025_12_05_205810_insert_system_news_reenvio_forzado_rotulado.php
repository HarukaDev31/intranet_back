<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\SystemNews;

class InsertSystemNewsReenvioForzadoRotulado extends Migration
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

        // Noticia: Reenvío forzado de rotulados
        DB::table('system_news')->insert([
            'title' => 'Funcionalidad de reenvío forzado de rotulados',
            'content' => 'Se ha implementado la funcionalidad de reenvío forzado de rotulados para proveedores. Ahora es posible reenviar todos los archivos de rotulado (PDFs, imágenes, mensajes de bienvenida, dirección, y archivos adicionales según el tipo de producto) a proveedores que ya recibieron el envío previamente. Esta funcionalidad es útil cuando se necesita volver a enviar la información completa del rotulado por alguna razón, como correcciones, cambios en los datos del proveedor, o solicitudes de reenvío. El sistema tratará el envío como si fuera la primera vez, incluyendo todos los archivos y mensajes previos.',
            'summary' => 'Nueva funcionalidad para reenviar rotulados completos a proveedores que ya recibieron el envío previamente.',
            'type' => 'feature',
            'is_published' => true,
            'published_at' => now()->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_COORDINACION,
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
            ->where('title', 'Funcionalidad de reenvío forzado de rotulados')
            ->delete();
    }
}
