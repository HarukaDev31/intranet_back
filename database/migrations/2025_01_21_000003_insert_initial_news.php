<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class InsertInitialNews extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = now();
        
        $news = [
            [
                'title' => 'Soporte para enviar mensajes de cambio de consolidado',
                'summary' => 'Se agregó la funcionalidad para enviar mensajes de cambio de consolidado a consolidados antiguos.',
                'content' => '<p>Se implementó una nueva funcionalidad que permite enviar mensajes de cambio de consolidado a consolidados antiguos, mejorando la comunicación y el seguimiento de cambios en el sistema.</p>',
                'type' => 'feature',
                'solicitada_por' => 'EQUIPO_DE_CURSO',
                'is_published' => true,
                'published_at' => $now,
                'redirect' => null,
                'created_by' => 1,
                'created_by_name' => 'Sistema',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'title' => 'Mejora visual en verificación de pagos de consolidado',
                'summary' => 'Los pagos en la sección de verificación de pagos de consolidado ahora son más llamativos y fáciles de identificar.',
                'content' => '<p>Se mejoró la interfaz visual de la sección de verificación de pagos de consolidado, haciendo que los pagos sean más llamativos y fáciles de identificar para una mejor experiencia de usuario.</p>',
                'type' => 'feature',
                'solicitada_por' => 'ADMINISTRACION',
                'is_published' => true,
                'published_at' => $now,
                'redirect' => '/verificacion',
                'created_by' => 1,
                'created_by_name' => 'Sistema',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'title' => 'Redirección a pagos desde cotización final',
                'summary' => 'Se agregó la opción de redireccionar directamente a la sección de pagos desde la cotización final.',
                'content' => '<p>Ahora es posible redirigir directamente a la sección de pagos desde la cotización final, facilitando el acceso rápido a esta funcionalidad y mejorando el flujo de trabajo.</p>',
                'type' => 'feature',
                'solicitada_por' => 'ADMINISTRACION',
                'is_published' => true,
                'published_at' => $now,
                'redirect' => '/cargaconsolidada/completados/cotizacion-final/132?tab=general',
                'created_by' => 1,
                'created_by_name' => 'Sistema',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'title' => 'Nuevo tipo de documento en pedir documentos',
                'summary' => 'Se agregó un nuevo tipo de documento disponible en la sección de pedir documentos de venta.',
                'content' => '<p>Se implementó un nuevo tipo de documento en la funcionalidad de pedir documentos de venta, ampliando las opciones disponibles para los usuarios y mejorando la gestión documental.</p>',
                'type' => 'feature',
                'solicitada_por' => 'EQUIPO_DE_COORDINACION',
                'is_published' => true,
                'published_at' => $now,
                'redirect' => null,
                'created_by' => 1,
                'created_by_name' => 'Sistema',
                'created_at' => $now,
                'updated_at' => $now
            ]
        ];

        DB::table('system_news')->insert($news);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar las noticias insertadas por título
        $titles = [
            'Soporte para enviar mensajes de cambio de consolidado',
            'Mejora visual en verificación de pagos de consolidado',
            'Redirección a pagos desde cotización final',
            'Nuevo tipo de documento en pedir documentos'
        ];

        DB::table('system_news')
            ->whereIn('title', $titles)
            ->delete();
    }
}

