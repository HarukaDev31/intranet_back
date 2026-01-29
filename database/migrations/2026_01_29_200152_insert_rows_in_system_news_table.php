<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\SystemNews;

class InsertRowsInSystemNewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Insertar dos noticias en la tabla `system_news`
        // Obtener el primer usuario disponible para created_by
        $firstUserId = DB::table('usuario')->value('ID_Usuario');
        if (!$firstUserId) {
            $firstUserId = 1;
        }

        DB::table('system_news')->insert([
            'title' => 'Nuevo apartado de calculadora para cotizadores',
            'content' => 'Hemos añadido un apartado de calculadora para cotizadores que facilita el cálculo rápido de precios, márgenes y descuentos. Permite simular distintos escenarios, aplicar reglas de negocio y generar una cotización base que luego se puede ajustar y convertir en una cotización formal.',
            'summary' => 'Calculadora para cotizadores: calcula precios, márgenes y descuentos de manera rápida y exportable.',
            'type' => 'feature',
            'is_published' => true,
            'published_at' => now()->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_CEO,
            'redirect' => '/cotizaciones',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        DB::table('system_news')->insert([
            'title' => 'Nuevo apartado de viaticos y reintegros',
            'content' => 'Hemos implementado un nuevo apartado para viáticos y reintegros que centraliza la solicitud, revisión y reembolso de gastos de viaje y otros desembolsos. Los usuarios pueden subir comprobantes, solicitar reintegros y hacer seguimiento del estado; el equipo de administración podrá revisar, confirmar o rechazar las solicitudes.',
            'summary' => 'Gestión de viáticos y reintegros: solicita, sube comprobantes y sigue el estado de tus reembolsos.',
            'type' => 'feature',
            'is_published' => true,
            'published_at' => now()->toDateString(),
            'created_by' => $firstUserId,
            'created_by_name' => 'Sistema',
            'solicitada_por' => SystemNews::SOLICITADA_POR_CEO,
            'redirect' => '/viaticos',
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
                'Nuevo apartado de calculadora para cotizadores',
                'nuevo apartado de viaticos y reintegros'
            ])
            ->delete();
    }
}
