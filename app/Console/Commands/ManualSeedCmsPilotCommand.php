<?php

namespace App\Console\Commands;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualPagina;
use Illuminate\Console\Command;

class ManualSeedCmsPilotCommand extends Command
{
    protected $signature = 'manual:seed-cms-pilot {--fresh : Borra páginas del piloto cotizador antes de recrear}';

    protected $description = 'Siembra piloto CMS: Cotizador → Carga consolidada (bloques UI)';

    public function handle(): int
    {
        $roleSlug = 'cotizador';
        $idGrupo = 1210;

        if ($this->option('fresh')) {
            ManualPagina::query()->where('role_slug', $roleSlug)->delete();
            $this->warn('Páginas cotizador CMS eliminadas.');
        }

        $page = ManualPagina::query()->updateOrCreate(
            [
                'role_slug' => $roleSlug,
                'modulo_key' => 'cargaconsolidada/abiertos',
            ],
            [
                'id_grupo' => $idGrupo,
                'titulo' => 'Carga consolidada — Abiertos',
                'descripcion' => 'Listado de cargas en curso y detalle Prospectos / Por Embarcar (vista Cotizador).',
                'orden' => 1,
                'publicado' => true,
            ]
        );

        // Recrear bloques del piloto
        $page->bloques()->delete();

        $blocks = [
            [
                'tipo' => ManualBloque::TIPO_TEXTO,
                'titulo' => 'Para qué sirve',
                'orden' => 1,
                'payload' => [
                    'body' => 'Desde aquí trabajas los contenedores de carga consolidada en curso: filtras el listado, abres una carga y gestionas Prospectos o Por Embarcar.',
                ],
            ],
            [
                'tipo' => ManualBloque::TIPO_UI_TOOLBAR,
                'titulo' => 'Acciones del listado',
                'orden' => 2,
                'payload' => [
                    'hint' => 'Barra superior del listado (ejemplo visual).',
                    'buttons' => [
                        ['label' => 'Exportar', 'color' => 'neutral', 'variant' => 'outline'],
                        ['label' => 'Actualizar', 'color' => 'neutral', 'variant' => 'ghost'],
                    ],
                ],
            ],
            [
                'tipo' => ManualBloque::TIPO_UI_FILTERS,
                'titulo' => 'Filtros del listado',
                'orden' => 3,
                'payload' => [
                    'hint' => 'Igual que en pantalla: acota por año y estado.',
                    'fields' => [
                        [
                            'type' => 'select',
                            'label' => 'Año',
                            'value' => '2026',
                            'options' => [
                                ['label' => '2025', 'value' => '2025'],
                                ['label' => '2026', 'value' => '2026'],
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => 'Estado',
                            'value' => 'PENDIENTE',
                            'options' => [
                                ['label' => 'PENDIENTE', 'value' => 'PENDIENTE'],
                                ['label' => 'RECIBIENDO', 'value' => 'RECIBIENDO'],
                                ['label' => 'COMPLETADO', 'value' => 'COMPLETADO'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'tipo' => ManualBloque::TIPO_UI_TABLE,
                'titulo' => 'Listado de cargas',
                'orden' => 4,
                'payload' => [
                    'hint' => 'Haz clic en el ojo (Acciones) para entrar al detalle.',
                    'columns' => ['Carga', 'Mes', 'Año', 'Estado', 'CBM Perú', 'Acciones'],
                    'rows' => [
                        ['CARGA CONSOLIDADA #12-2026', 'AGOSTO', '2026', 'PENDIENTE', '42.5', '👁'],
                        ['CARGA CONSOLIDADA #11-2026', 'JULIO', '2026', 'RECIBIENDO', '38.1', '👁'],
                    ],
                ],
            ],
            [
                'tipo' => ManualBloque::TIPO_UI_TABS,
                'titulo' => 'Dentro de la carga (Cotizador)',
                'orden' => 5,
                'payload' => [
                    'hint' => 'Al abrir una carga entras directo a Prospectos.',
                    'active' => 'prospectos',
                    'tabs' => [
                        [
                            'key' => 'prospectos',
                            'label' => 'Prospectos',
                            'content' => 'Aquí creas prospectos, subes Excel, cambias estado (PENDIENTE / CONTACTADO / CONFIRMADO), copias enlace de firma y abres Documentación.',
                        ],
                        [
                            'key' => 'embarcar',
                            'label' => 'Por Embarcar',
                            'content' => 'Seguimiento hacia embarque: estado de coordinación, productos, fechas, CBM, peso y proveedor.',
                        ],
                    ],
                ],
            ],
            [
                'tipo' => ManualBloque::TIPO_UI_TOOLBAR,
                'titulo' => 'En Prospectos',
                'orden' => 6,
                'payload' => [
                    'hint' => 'Botones típicos dentro de la pestaña Prospectos.',
                    'buttons' => [
                        ['label' => 'Crear Prospecto', 'color' => 'primary', 'variant' => 'solid'],
                        ['label' => 'Copiar enlace de firma', 'color' => 'neutral', 'variant' => 'outline'],
                        ['label' => 'Documentación', 'color' => 'neutral', 'variant' => 'outline'],
                        ['label' => 'Mover cotización', 'color' => 'neutral', 'variant' => 'ghost'],
                    ],
                ],
            ],
            [
                'tipo' => ManualBloque::TIPO_UI_FILTERS,
                'titulo' => 'Filtros en Prospectos',
                'orden' => 7,
                'payload' => [
                    'fields' => [
                        [
                            'type' => 'select',
                            'label' => 'Estado Cotizador',
                            'value' => 'Todos',
                            'options' => [
                                ['label' => 'Todos', 'value' => 'Todos'],
                                ['label' => 'PENDIENTE', 'value' => 'PENDIENTE'],
                                ['label' => 'CONTACTADO', 'value' => 'CONTACTADO'],
                                ['label' => 'CONFIRMADO', 'value' => 'CONFIRMADO'],
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => 'Estado Proveedor',
                            'value' => 'WAIT',
                            'options' => [
                                ['label' => 'WAIT', 'value' => 'WAIT'],
                                ['label' => 'INSPECTION', 'value' => 'INSPECTION'],
                                ['label' => 'LOADED', 'value' => 'LOADED'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'tipo' => ManualBloque::TIPO_UI_CALLOUT,
                'titulo' => null,
                'orden' => 8,
                'payload' => [
                    'tone' => 'info',
                    'title' => 'Tip',
                    'body' => 'Como Cotizador no ves el menú de “pasos” de otros roles: vas directo a Prospectos / Por Embarcar.',
                ],
            ],
            [
                'tipo' => ManualBloque::TIPO_UI_CALLOUT,
                'titulo' => null,
                'orden' => 9,
                'payload' => [
                    'tone' => 'warning',
                    'title' => 'Si algo no funciona',
                    'body' => 'No encuentras una carga → revisa Año/Estado. Falta un botón → depende del estado del prospecto o de tus permisos. El Excel no sube → cierra el archivo en Excel e inténtalo de nuevo.',
                ],
            ],
            [
                'tipo' => ManualBloque::TIPO_MEDIA_SHOT,
                'titulo' => 'Captura de pantalla',
                'orden' => 10,
                'payload' => [
                    'alt' => 'Listado Carga consolidada Abiertos',
                    'caption' => 'Captura pendiente: cuando subas la imagen desde el mantenedor, aparecerá aquí.',
                    'url' => null,
                    'placeholder' => true,
                ],
            ],
        ];

        foreach ($blocks as $block) {
            ManualBloque::query()->create([
                'pagina_id' => $page->id,
                'tipo' => $block['tipo'],
                'titulo' => $block['titulo'],
                'payload' => $block['payload'],
                'orden' => $block['orden'],
            ]);
        }

        // Segunda página corta: Completados
        $page2 = ManualPagina::query()->updateOrCreate(
            [
                'role_slug' => $roleSlug,
                'modulo_key' => 'cargaconsolidada/completados',
            ],
            [
                'id_grupo' => $idGrupo,
                'titulo' => 'Carga consolidada — Completados',
                'descripcion' => 'Consulta de cargas ya cerradas.',
                'orden' => 2,
                'publicado' => true,
            ]
        );
        $page2->bloques()->delete();
        ManualBloque::query()->create([
            'pagina_id' => $page2->id,
            'tipo' => ManualBloque::TIPO_TEXTO,
            'titulo' => 'Para qué sirve',
            'payload' => [
                'body' => 'Revisa cargas terminadas. Filtra, abre el detalle con el ojo y consulta la información histórica.',
            ],
            'orden' => 1,
        ]);
        ManualBloque::query()->create([
            'pagina_id' => $page2->id,
            'tipo' => ManualBloque::TIPO_UI_FILTERS,
            'titulo' => 'Filtros',
            'payload' => [
                'fields' => [
                    [
                        'type' => 'select',
                        'label' => 'Año',
                        'value' => '2026',
                        'options' => [
                            ['label' => '2025', 'value' => '2025'],
                            ['label' => '2026', 'value' => '2026'],
                        ],
                    ],
                ],
            ],
            'orden' => 2,
        ]);
        ManualBloque::query()->create([
            'pagina_id' => $page2->id,
            'tipo' => ManualBloque::TIPO_MEDIA_SHOT,
            'titulo' => 'Captura',
            'payload' => [
                'alt' => 'Completados',
                'caption' => 'Captura pendiente.',
                'url' => null,
                'placeholder' => true,
            ],
            'orden' => 3,
        ]);

        $this->info("Piloto CMS listo: {$roleSlug} ({$page->bloques()->count()} + {$page2->bloques()->count()} bloques).");

        return 0;
    }
}
