<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\SystemNews;

class InsertSystemNewsFixAutofirmaContratos extends Migration
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

        // Noticia: Fix del autofirma de contratos
        DB::table('system_news')->insert([
            'title' => 'Corrección y mejoras en el sistema de autofirma de contratos',
            'content' => 'Se han realizado correcciones y mejoras importantes en el sistema de autofirma automática de contratos. El proceso ahora incluye validaciones mejoradas para evitar duplicados, verificación de existencia de archivos necesarios (imagen de auto-aceptación y logo), manejo de errores más robusto con logging detallado, y configuración optimizada de memoria y tiempo límite para el renderizado de PDFs. El sistema ahora procesa automáticamente las cotizaciones confirmadas hace 2 o más días que aún no tienen contrato firmado, generando los contratos auto-firmados de manera eficiente y confiable. El comando se ejecuta automáticamente cada 5 minutos para mantener el proceso actualizado.',
            'summary' => 'Mejoras en validaciones, manejo de errores y optimización del proceso de autofirma automática de contratos.',
            'type' => 'fix',
            'is_published' => true,
            'published_at' => now()->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_TI,
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
            ->where('title', 'Corrección y mejoras en el sistema de autofirma de contratos')
            ->delete();
    }
}
