<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualPagina;

/**
 * Escribe un artículo CMS con la plantilla estándar (QA, pasos, acordeones, resultado).
 */
class ManualUsuarioArticuloWriter
{
    /**
     * @param  array $role  slug, id_grupo, nombre
     * @param  array $screen definición de pantalla (ver ManualUsuarioScreensCatalog)
     * @param  int   $orden
     * @return array{page_id:int, created:bool}
     */
    public function seed(array $role, array $screen, $orden = 1)
    {
        $created = false;
        $page = ManualPagina::query()->firstOrNew([
            'role_slug' => $role['slug'],
            'modulo_key' => $screen['modulo_key'],
        ]);
        if (!$page->exists) {
            $created = true;
        }

        $page->id_grupo = $role['id_grupo'];
        $page->titulo = $this->fill($screen['titulo'], $role);
        $page->descripcion = $this->fill(isset($screen['descripcion']) ? $screen['descripcion'] : '', $role);
        $page->orden = $orden;
        $page->publicado = true;
        $page->save();

        $this->wipeBlocks((int) $page->id);

        $tags = isset($screen['tags']) ? $screen['tags'] : [];
        $tags = array_values(array_filter(array_merge(
            ['Rol: ' . $role['nombre']],
            $tags
        )));

        $root = $this->block($page->id, null, ManualBloque::TIPO_GRUPO, $screen['articulo_titulo'], 1, [
            'subtitulo' => null,
            'snapshot' => [
                'variant' => 'articulo',
                'tags' => $tags,
            ],
        ], isset($screen['articulo_clave']) ? $screen['articulo_clave'] : null);

        $n = 1;
        $this->qa($page->id, $root->id, $n++, '¿Qué es?', $this->fill($this->pick($screen, $role, 'que_es'), $role));
        $this->qa($page->id, $root->id, $n++, '¿Para qué sirve?', $this->fill($this->pick($screen, $role, 'para_que'), $role));
        $this->qa($page->id, $root->id, $n++, '¿Quién lo utiliza?', $this->fill(
            $this->pick($screen, $role, 'quien', 'Rol {rol}.'),
            $role
        ));
        $this->qa($page->id, $root->id, $n++, '¿Cuándo utilizarlo?', $this->fill($this->pick($screen, $role, 'cuando'), $role));

        $pasos = $this->pasosForRole($screen, $role);

        // Si hay pasos del detalle, el menú usa esos flujos (para qué de cada paso).
        // Los procedimientos del listado no se escriben como flow para no duplicar hojas.
        if (!$pasos) {
            foreach (isset($screen['flows']) ? $screen['flows'] : [] as $flow) {
                $flowTitle = $this->stripPasosPrefix($this->fill($flow['titulo'], $role));
                $this->writeFlowWithCapturas(
                    $page->id,
                    $root->id,
                    $n,
                    $flowTitle,
                    isset($flow['steps']) && is_array($flow['steps']) ? $flow['steps'] : [],
                    $role,
                    '',
                    $screen
                );
            }
        }
        foreach ($pasos as $paso) {
            $pasoTitulo = $this->stripPasosPrefix($this->fill(isset($paso['titulo']) ? $paso['titulo'] : 'Paso', $role));
            $rawSteps = [];
            if (isset($paso['steps']) && is_array($paso['steps'])) {
                $rawSteps = $paso['steps'];
            } elseif (!empty($paso['body'])) {
                $rawSteps = [$paso['body']];
            }
            $hint = isset($paso['hint']) ? $this->fill($paso['hint'], $role) : '';
            $this->writeFlowWithCapturas($page->id, $root->id, $n, $pasoTitulo, $rawSteps, $role, $hint, $screen);
        }

        $campos = $this->block($page->id, $root->id, ManualBloque::TIPO_GRUPO, 'Campos que deben completarse', $n++, [
            'subtitulo' => null,
            'snapshot' => ['colapsable' => true],
        ], 'campos');
        $this->block($page->id, $campos->id, ManualBloque::TIPO_TABLA, null, 1, [
            'subtitulo' => null,
            'snapshot' => [
                'variant' => 'doc',
                'columns' => ['Campo', 'Cómo se completa', 'Ejemplo'],
                'rows' => $this->pick($screen, $role, 'campos', [
                    ['pendiente de definir', 'pendiente de definir', 'pendiente de definir'],
                ]),
            ],
        ]);

        $consideraciones = $this->block($page->id, $root->id, ManualBloque::TIPO_GRUPO, 'Consideraciones importantes', $n++, [
            'subtitulo' => null,
            'snapshot' => ['colapsable' => true],
        ], 'consideraciones');
        $this->block($page->id, $consideraciones->id, ManualBloque::TIPO_TEXTO, null, 1, [
            'subtitulo' => null,
            'snapshot' => [
                'body' => $this->fill($this->pick($screen, $role, 'consideraciones', 'pendiente de definir'), $role),
            ],
        ]);

        $errores = $this->block($page->id, $root->id, ManualBloque::TIPO_GRUPO, 'Errores frecuentes', $n++, [
            'subtitulo' => null,
            'snapshot' => ['colapsable' => true],
        ], 'errores');
        $this->block($page->id, $errores->id, ManualBloque::TIPO_TABLA, null, 1, [
            'subtitulo' => null,
            'snapshot' => [
                'variant' => 'doc',
                'columns' => ['Situación', 'Causa probable', 'Solución'],
                'rows' => $this->pick($screen, $role, 'errores', [
                    ['pendiente de definir', 'pendiente de definir', 'pendiente de definir'],
                ]),
            ],
        ]);
        $this->block($page->id, $errores->id, ManualBloque::TIPO_CALLOUT, null, 2, [
            'subtitulo' => null,
            'snapshot' => [
                'tone' => 'warning',
                'title' => 'Completar con el equipo',
                'body' => isset($screen['errores_nota'])
                    ? $this->fill($screen['errores_nota'], $role)
                    : 'Si falta un caso real, anótalo con soporte antes de publicar.',
            ],
        ]);

        $this->qa($page->id, $root->id, $n++, 'Ejemplo práctico (datos ficticios)', $this->fill(
            $this->pick($screen, $role, 'ejemplo', 'pendiente de definir'),
            $role
        ));

        $this->block($page->id, $root->id, ManualBloque::TIPO_CALLOUT, null, $n++, [
            'subtitulo' => null,
            'snapshot' => [
                'tone' => 'success',
                'title' => 'Resultado esperado:',
                'body' => $this->fill($this->pick($screen, $role, 'resultado', 'pendiente de definir'), $role),
            ],
        ]);

        $this->qa($page->id, $root->id, $n++, 'Ver también', $this->fill(
            $this->pick($screen, $role, 'ver_tambien', 'pendiente de definir'),
            $role
        ));

        return ['page_id' => (int) $page->id, 'created' => $created];
    }

    private function pick(array $screen, array $role, $field, $default = '')
    {
        $slug = isset($role['slug']) ? $role['slug'] : '';
        $byRoleKey = $field . '_por_rol';
        if ($slug !== '' && isset($screen[$byRoleKey]) && is_array($screen[$byRoleKey]) && array_key_exists($slug, $screen[$byRoleKey])) {
            return $screen[$byRoleKey][$slug];
        }

        return isset($screen[$field]) ? $screen[$field] : $default;
    }

    private function pasosForRole(array $screen, array $role)
    {
        $slug = isset($role['slug']) ? $role['slug'] : '';
        if (isset($screen['pasos_por_rol'][$slug]) && is_array($screen['pasos_por_rol'][$slug])) {
            return $screen['pasos_por_rol'][$slug];
        }
        return isset($screen['pasos']) && is_array($screen['pasos']) ? $screen['pasos'] : [];
    }

    private function writeFlowWithCapturas(
        $paginaId,
        $parentId,
        &$n,
        $titulo,
        array $rawSteps,
        array $role,
        $hint = '',
        array $screen = []
    )
    {
        $steps = [];
        $captureSteps = [];
        foreach ($rawSteps as $step) {
            $norm = $this->normalizeFlowStep($step, $role);
            if ($norm['title'] === '' && $norm['body'] === '') {
                continue;
            }
            $steps[] = $norm;
            $captureSteps[] = $step;
        }
        if (!$steps) {
            return;
        }
        $snapshot = ['steps' => $steps];
        if ($hint !== '') {
            $snapshot['hint'] = $hint;
        }
        $flow = $this->block($paginaId, $parentId, ManualBloque::TIPO_FLOW, $titulo, $n++, [
            'subtitulo' => null,
            'snapshot' => $snapshot,
        ]);
        $m = 1;
        foreach ($captureSteps as $i => $raw) {
            $hintCap = $this->capturaHint($titulo, $raw, $i + 1, $role);
            $stepTitle = isset($steps[$i]['title']) && $steps[$i]['title'] !== ''
                ? $steps[$i]['title']
                : 'Paso ' . ($i + 1);
            $roleSlug = isset($role['slug']) ? (string) $role['slug'] : '';
            $screenKey = isset($screen['screen_key'])
                ? (string) $screen['screen_key']
                : (isset($screen['key']) ? (string) $screen['key'] : (isset($screen['modulo_key']) ? (string) $screen['modulo_key'] : ''));
            $captureKey = ManualUsuarioCaptureKey::make(
                isset($screen['modulo_key']) ? (string) $screen['modulo_key'] : '',
                $roleSlug,
                (string) $titulo,
                $stepTitle,
                $i + 1,
                is_array($raw) && isset($raw['capture_key']) ? (string) $raw['capture_key'] : null
            );
            $aliasOf = is_array($raw) && !empty($raw['capture_alias_of'])
                ? (string) $raw['capture_alias_of']
                : null;
            $identity = ManualUsuarioCaptureKey::identity($captureKey, $aliasOf);
            $output = is_array($raw) && !empty($raw['capture_output'])
                ? (string) $raw['capture_output']
                : ManualUsuarioCaptureKey::output($identity ?: $captureKey);
            $this->mediaPlantilla(
                $paginaId,
                $flow->id,
                $m++,
                $hintCap['titulo'],
                $hintCap['subtitulo'],
                $hintCap['caption'],
                [
                    'capture_key' => $captureKey,
                    'role' => $roleSlug,
                    'screen' => $screenKey,
                    'screen_url' => isset($screen['articulo_clave']) ? (string) $screen['articulo_clave'] : '',
                    'modulo' => isset($screen['modulo_key']) ? (string) $screen['modulo_key'] : '',
                    'flow' => (string) $titulo,
                    'step' => ['number' => $i + 1, 'title' => $stepTitle],
                    'hint' => $hintCap['caption'],
                    'output' => $output,
                    'config' => $this->captureConfig($raw),
                    'alias_of' => $aliasOf,
                ]
            );
        }
    }

    private function captureConfig($raw)
    {
        if (!is_array($raw)) {
            return [];
        }

        $config = [];
        foreach ([
            'type',
            'target',
            'actions',
            'expectedText',
            'padding',
            'masks',
            'piiAllow',
            'expectedHash',
            'enabled',
            'url',
        ] as $field) {
            if (array_key_exists($field, $raw)) {
                $config[$field] = $raw[$field];
            }
        }

        return $config;
    }

    private function capturaHint($pasoTitulo, $raw, $num, array $role)
    {
        $accion = '';
        $captura = '';
        if (is_array($raw)) {
            $accion = isset($raw['title']) ? trim((string) $raw['title']) : '';
            $captura = isset($raw['captura']) ? trim((string) $raw['captura']) : '';
        }
        if ($accion === '' && !is_array($raw)) {
            $accion = 'Vista de la pantalla';
        }
        if ($accion === '') {
            $accion = 'Paso ' . $num;
        }
        $tituloFoto = 'Foto ' . $num . ' — ' . $accion;
        $subtitulo = 'Subir: ' . $pasoTitulo . ' — ' . $num . '. ' . $accion;
        $caption = $captura !== ''
            ? $captura
            : 'Recorta esa acción en pantalla. Datos ficticios, sin nombres reales de clientes.';

        return [
            'titulo' => $this->fill($tituloFoto, $role),
            'subtitulo' => $this->fill($subtitulo, $role),
            'caption' => $this->fill($caption, $role),
        ];
    }

    private function normalizeFlowStep($step, array $role)
    {
        if (is_array($step)) {
            return [
                'title' => $this->fill(isset($step['title']) ? $step['title'] : '', $role),
                'body' => $this->fill(isset($step['body']) ? $step['body'] : '', $role),
            ];
        }

        return ['title' => '', 'body' => $this->fill((string) $step, $role)];
    }

    private function stripPasosPrefix($titulo)
    {
        return trim(preg_replace('/^Pasos\s*[—–-]?\s*/u', '', (string) $titulo));
    }

    private function mediaPlantilla(
        $paginaId,
        $parentId,
        $orden,
        $titulo,
        $subtitulo = null,
        $caption = null,
        array $capture = []
    )
    {
        if ($subtitulo === null) {
            $subtitulo = 'Subir esta captura en el mantenedor.';
        }
        if ($caption === null) {
            $caption = 'Recorta esa acción. Datos ficticios, sin nombres reales.';
        }

        $snapshot = [
            'caption' => $caption,
            'alt' => $titulo,
            'media_id' => null,
        ];
        foreach ($capture as $field => $value) {
            if ($value !== null && $value !== '') {
                $snapshot[$field === 'capture_key' ? 'capture_key' : 'capture_' . $field] = $value;
            }
        }

        return $this->block($paginaId, $parentId, ManualBloque::TIPO_MEDIA, $titulo, $orden, [
            'subtitulo' => $subtitulo,
            'snapshot' => $snapshot,
        ], 'captura');
    }

    private function fill($text, array $role)
    {
        return strtr((string) $text, [
            '{rol}' => $role['nombre'],
            '{slug}' => $role['slug'],
        ]);
    }

    private function wipeBlocks($paginaId)
    {
        ManualBloque::query()->where('pagina_id', $paginaId)->update(['parent_id' => null]);
        ManualBloque::query()->where('pagina_id', $paginaId)->delete();
    }

    private function block($paginaId, $parentId, $tipo, $titulo, $orden, array $payload, $clave = null)
    {
        return ManualBloque::query()->create([
            'pagina_id' => $paginaId,
            'parent_id' => $parentId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'clave' => $clave,
            'payload' => $payload,
            'orden' => $orden,
        ]);
    }

    private function qa($paginaId, $parentId, $orden, $titulo, $body)
    {
        return $this->block($paginaId, $parentId, ManualBloque::TIPO_TEXTO, $titulo, $orden, [
            'subtitulo' => null,
            'snapshot' => ['qa' => true, 'body' => $body],
        ]);
    }
}
