<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\SystemNews;

class InsertSystemNewsConsolidadoBusqueda extends Migration
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

        //type: feature, update, fix, announcement

        // Noticia 1: Implementacion de año del consolidado y nuevo ordenamiento a la base de productos
        DB::table('system_news')->insert([
            'title' => 'Implementacion de año del consolidado y nuevo ordenamiento a la base de productos',
            'content' => 'Se ha implementado el campo "año del consolidado" en el flujo de consolidación para facilitar reconocimiento, reporte y navegación por períodos. Además se aplicó un nuevo ordenamiento en la base de productos para mejorar la consistencia y la experiencia al buscar y listar productos. Esta mejora incluye la normalización de nombres y la preparación para futuros filtros por año, lo que permitirá reportes históricos y migraciones de datos más sencillas. Si notas algún fallo, contacta al equipo de soporte TI.',
            'summary' => 'Se añadió el campo año en consolidado y se reordenó la base de productos para mejor consistencia y reportes.',
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

        // Noticia 2: Se mejoró la velocidad en la que responde el buscador de la base de datos
        DB::table('system_news')->insert([
            'title' => 'Se mejoró la velocidad en la que responde el buscador de la base de datos',
            'content' => 'Hemos optimizado las consultas del buscador de la base de datos para reducir tiempos de respuesta. Los cambios incluyen ajustes en consultas SQL, reducción de operaciones N+1, y la adición de índices en las columnas más consultadas. Como resultado, las búsquedas devuelven resultados más rápido y con menor carga en la base de datos, mejorando la experiencia del usuario en listados y filtros. Si detectas algún caso con rendimiento subóptimo, por favor reportarlo con un ejemplo concreto para investigarlo.',
            'summary' => 'Optimización del buscador: consultas mejoradas, índices y reducción de N+1 para respuestas más rápidas.',
            'type' => 'update',
            'is_published' => true,
            'published_at' => now()->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_CEO,
            'redirect' => '/news',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Noticia 3: Visualizacion responsiva mobile completada para perfil Coordinación
        DB::table('system_news')->insert([
            'title' => 'Visualizacion Responsiva Mobile Completada para perfil Coordinación',
            'content' => 'Se completó la adaptación responsiva de la interfaz para el perfil de Coordinación en dispositivos móviles. Los cambios incluyen mejoras en el layout, navegación optimizada, botones y tablas adaptadas, y reorganización de elementos para facilitar la gestión desde teléfonos android y IOS. Esto permite a los usuarios de Coordinación realizar revisiones, aprobar y gestionar proveedores de manera eficiente desde móviles sin perder funcionalidad. Reporta cualquier comportamiento inesperado para que podamos pulir la experiencia.',
            'summary' => 'Interfaz responsiva para perfil Coordinación: layout y controles optimizados para móvil.',
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

        // Noticia 4: Envío de mensajes desde el perfil administración
        DB::table('system_news')->insert([
            'title' => 'Envío de mensajes desde el perfil administración',
            'content' => 'Se ha implementado la funcionalidad de envío de mensajes desde el perfil de administración. Ahora los usuarios con permisos de administración pueden enviar mensajes de recordatorio y cobro directamente desde la plataforma, facilitando la comunicación con los clientes durante el proceso de entrega y gestión de pagos. Esta mejora permite un mejor control y seguimiento de las comunicaciones enviadas, optimizando el flujo de trabajo del equipo administrativo.',
            'summary' => 'Nueva funcionalidad para enviar mensajes de recordatorio y cobro desde el perfil administración.',
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
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('system_news')
            ->whereIn('title', [
                'Implementacion de año del consolidado y nuevo ordenamiento a la base de productos',
                'Se mejoró la velocidad en la que responde el buscador de la base de datos',
                'Visualizacion Responsiva Mobile Completada para perfil Coordinación',
                'Envío de mensajes desde el perfil administración'
            ])
            ->delete();
    }
}
