<?php

namespace Database\Seeders;

use App\Models\WebCursoPlan;
use Illuminate\Database\Seeder;

class WebCursoPlanSeeder extends Seeder
{
    public function run(): void
    {
        $page = WebCursoPlan::PAGE_CURSO_MEMBRESIA;

        $defaults = [
            [
                'tipo_pago' => 1,
                'title' => 'Plan Emprendedor',
                'subtitle' => 'Clase grabada',
                'price_current' => 'S/ 200',
                'price_original' => null,
                'is_visible' => true,
                'sort_order' => 10,
                'button_label' => 'Ir a pagar',
                'button_css_classes' => 'border-0 py-3 text-center d-block fw-bold hover-naranja text-decoration-none small2 bg-dark text-white w-100 mt-3 rounded-3',
                'card_css_classes' => 'bg-light c-plam col-12',
                'benefits' => [
                    'Aula virtual por 1 año.',
                    'Herramientas de Trabajo.',
                    'Ayuda Importadora por 3 meses.',
                    'Lista de contactos.',
                    'Lista de Proveedores.',
                    'Manual de Importaciones.',
                    'Alianza Casillero en China.',
                    'Alianza Casillero en USA.',
                    'Boletín informativo por 1 año.',
                ],
            ],
            [
                'tipo_pago' => 2,
                'title' => 'Plan Empresarial',
                'subtitle' => 'Clase en Vivo (4 días en Zoom)',
                'price_current' => 'S/ 385',
                'price_original' => 'S/ 550',
                'is_visible' => false,
                'sort_order' => 20,
                'button_label' => 'Ir a pagar',
                'button_css_classes' => 'border-0 py-3 text-center d-block fw-bold hover-naranja text-decoration-none small2 bg-morado text-white w-100 mt-3 rounded-3',
                'card_css_classes' => 'bg-light c-plam shadow-sm col-12 borde-naranja',
                'benefits' => [
                    'Asesoría en tu tienda virtual.',
                    'Aula virtual por 1 año.',
                    'Herramientas de Trabajo.',
                    'Ayuda Importadora por 3 meses.',
                    'Lista de contactos.',
                    'Lista de Proveedores.',
                    'Manual de Importaciones.',
                    'Alianza Casillero en China.',
                    'Alianza Casillero en USA.',
                    'Boletín informativo por 1 año.',
                    'Importación grupal.',
                ],
            ],
            [
                'tipo_pago' => 3,
                'title' => 'Plan Pro Business',
                'subtitle' => 'Clase en Vivo (4 días en Zoom)',
                'price_current' => 'S/ 385',
                'price_original' => 'S/ 550',
                'is_visible' => true,
                'sort_order' => 30,
                'button_label' => 'Ir a pagar',
                'button_css_classes' => 'border-0 py-3 text-center d-block fw-bold hover-naranja text-decoration-none small2 bg-dark text-white w-100 mt-3 rounded-3',
                'card_css_classes' => 'bg-light c-plam col-12',
                'benefits' => [
                    'Asesoría en tu Tienda Virtual.',
                    'Aula virtual por 1 año.',
                    'Herramientas de Trabajo.',
                    'Ayuda Importadora por 3 meses.',
                    'Lista de contactos.',
                    'Lista de Proveedores.',
                    'Manual de Importaciones.',
                    'Alianza Casillero en China.',
                    'Alianza Casillero en USA.',
                    'Acceso de productos novedosos mensuales.',
                    'Tarifas preferenciales en todos nuestros servicios.',
                    'Asesoramiento en tu viaje a China.',
                ],
            ],
        ];

        foreach ($defaults as $row) {
            WebCursoPlan::query()->updateOrCreate(
                [
                    'page_key' => $page,
                    'tipo_pago' => $row['tipo_pago'],
                ],
                array_merge($row, ['page_key' => $page])
            );
        }
    }
}
