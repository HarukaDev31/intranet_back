<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\SystemNews;

class InsertSystemNewsCorreccionesMejoras extends Migration
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

        $now = now();

        // Noticia 1: Corrección del doble menú para el perfil coordinación
        DB::table('system_news')->insert([
            'title' => 'Corrección del doble menú para el perfil Coordinación',
            'content' => '<p>Se corrigió un problema donde aparecía un menú duplicado en el perfil de Coordinación. Ahora el menú se muestra correctamente de forma única, mejorando la navegación y la experiencia de usuario para el equipo de coordinación.</p>',
            'summary' => 'Se corrigió el problema del menú duplicado en el perfil de Coordinación.',
            'type' => 'fix',
            'is_published' => true,
            'published_at' => $now->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_COORDINACION,
            'redirect' => null,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        // Noticia 2: Indicador de si un número está activo
        DB::table('system_news')->insert([
            'title' => 'Indicador de estado activo para números',
            'content' => '<p>Se agregó un indicador visual que muestra si un número está activo o inactivo en el sistema. Esta mejora facilita la identificación rápida del estado de los números y mejora la gestión de la información.</p>',
            'summary' => 'Se agregó un indicador visual para mostrar si un número está activo.',
            'type' => 'feature',
            'is_published' => true,
            'published_at' => $now->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_COORDINACION,
            'redirect' => null,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        // Noticia 3: Redirección al indicador de número activo
        DB::table('system_news')->insert([
            'title' => 'Redirección al indicador de número activo',
            'content' => '<p>Se agregó la funcionalidad de redirección directa al indicador de número activo, permitiendo un acceso rápido y eficiente a esta información desde diferentes secciones del sistema.</p>',
            'summary' => 'Se implementó la redirección directa al indicador de número activo.',
            'type' => 'feature',
            'is_published' => true,
            'published_at' => $now->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_COORDINACION,
            'redirect' => null,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        // Noticia 4: Corrección del cliente duplicado en entregas
        DB::table('system_news')->insert([
            'title' => 'Corrección del cliente duplicado en entregas',
            'content' => '<p>Se corrigió un problema donde los clientes aparecían duplicados en la sección de entregas. Ahora cada cliente se muestra una sola vez, mejorando la claridad y evitando confusiones en el proceso de gestión de entregas.</p>',
            'summary' => 'Se corrigió el problema de clientes duplicados en la sección de entregas.',
            'type' => 'fix',
            'is_published' => true,
            'published_at' => $now->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_COORDINACION,
            'redirect' => null,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        // Noticia 5: Botones de documentación como filas individuales
        DB::table('system_news')->insert([
            'title' => 'Botones de documentación como filas individuales',
            'content' => '<p>Se mejoró la visualización de los botones de documentación, mostrándolos ahora cada uno como una fila individual. Esta mejora facilita la identificación y acceso a cada documento, mejorando la organización y la experiencia de usuario en la gestión documental.</p>',
            'summary' => 'Los botones de documentación ahora se muestran cada uno como una fila individual.',
            'type' => 'feature',
            'is_published' => true,
            'published_at' => $now->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_DOCUMENTACION,
            'redirect' => null,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        // Noticia 6: Filtro para el tab de delivery en la vista de entregas
        DB::table('system_news')->insert([
            'title' => 'Filtro para el tab de delivery en la vista de entregas',
            'content' => '<p>Se agregó un filtro para el tab de delivery en la vista de entregas para el perfil de administración. Esta mejora permite filtrar y buscar información de manera más eficiente en la sección de entregas, facilitando la gestión y el seguimiento de los deliveries.</p>',
            'summary' => 'Se agregó un filtro para el tab de delivery en la vista de entregas para administración.',
            'type' => 'feature',
            'is_published' => true,
            'published_at' => $now->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_ADMINISTRACION,
            'redirect' => null,
            'created_at' => $now,
            'updated_at' => $now
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
                'Corrección del doble menú para el perfil Coordinación',
                'Indicador de estado activo para números',
                'Redirección al indicador de número activo',
                'Corrección del cliente duplicado en entregas',
                'Botones de documentación como filas individuales',
                'Filtro para el tab de delivery en la vista de entregas'
            ])
            ->delete();
    }
}
