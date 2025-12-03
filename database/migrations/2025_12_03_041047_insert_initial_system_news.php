<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\SystemNews;
class InsertInitialSystemNews extends Migration
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
            // Si no hay usuarios, usar 1 como valor por defecto
            $firstUserId = 1;
        }

        // Insertar noticia sobre el apartado de noticias
        DB::table('system_news')->insert([
            'title' => 'Nuevo apartado de Noticias',
            'content' => 'Hemos creado un nuevo apartado de noticias del sistema donde podrás encontrar todas las actualizaciones, nuevas funcionalidades, correcciones y anuncios importantes. Este espacio te mantendrá informado sobre todos los cambios y mejoras que realizamos en la plataforma. Puedes acceder a este apartado desde /news.',
            'summary' => 'Nuevo apartado de noticias del sistema para mantenerte informado sobre actualizaciones y mejoras. Accede desde /news.',
            'type' => 'feature',
            'is_published' => true,
            'published_at' => now()->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_CEO,
            'redirect' => '/news',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Insertar noticia sobre el calendario
        DB::table('system_news')->insert([
            'title' => 'Nuevo módulo de Calendario',
            'content' => 'Hemos implementado un nuevo módulo de calendario que te permitirá gestionar eventos, programar actividades y mantener un registro organizado de todas tus tareas importantes. Puedes crear eventos públicos o privados, asignarlos a roles específicos y configurar recordatorios. Puedes acceder a este módulo desde /calendar.',
            'summary' => 'Nuevo módulo de calendario para gestionar eventos y actividades de manera organizada. Accede desde /calendar.',
            'type' => 'feature',
            'is_published' => true,
            'published_at' => now()->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_CEO,
            'redirect' => '/calendar',
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
        // Eliminar las noticias insertadas
        DB::table('system_news')
            ->whereIn('title', [
                'Nuevo apartado de Noticias',
                'Nuevo módulo de Calendario'
            ])
            ->delete();
    }
}
