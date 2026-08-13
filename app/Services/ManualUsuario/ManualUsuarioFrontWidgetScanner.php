<?php

namespace App\Services\ManualUsuario;

use Illuminate\Support\Str;

/**
 * Escanea pages/components Vue del front y arma catálogo page → widgets (DataTable/filtros).
 */
class ManualUsuarioFrontWidgetScanner
{
    public function frontPath(): string
    {
        $configured = (string) config('manual_usuario.front_path', '');
        if ($configured !== '' && is_dir($configured)) {
            return rtrim($configured, DIRECTORY_SEPARATOR);
        }

        $sibling = dirname(base_path()) . DIRECTORY_SEPARATOR . 'probusiness_intranetv3';
        if (is_dir($sibling)) {
            return $sibling;
        }

        return '';
    }

    /**
     * @return array{pages: array<int, array<string, mixed>>, stats: array<string, int>}
     */
    public function scan(?string $frontPath = null, ?string $onlyPrefix = null): array
    {
        $root = $frontPath ?: $this->frontPath();
        if ($root === '' || !is_dir($root)) {
            throw new \RuntimeException('No se encontró el front. Configura manual_usuario.front_path o coloca probusiness_intranetv3 como hermano del back.');
        }

        $pagesDir = $root . DIRECTORY_SEPARATOR . 'pages';
        if (!is_dir($pagesDir)) {
            throw new \RuntimeException("No existe pages/ en {$root}");
        }

        $pageFiles = $this->listVueFiles($pagesDir);
        $catalog = [];
        $stats = [
            'pages_scanned' => 0,
            'pages_with_widgets' => 0,
            'widgets' => 0,
            'datatables' => 0,
            'filters' => 0,
        ];

        foreach ($pageFiles as $absolute) {
            $rel = $this->relativePath($root, $absolute);
            if ($onlyPrefix && !str_starts_with(str_replace('\\', '/', $rel), str_replace('\\', '/', $onlyPrefix))) {
                continue;
            }

            $stats['pages_scanned']++;
            $content = @file_get_contents($absolute);
            if ($content === false) {
                continue;
            }

            $componentPaths = $this->resolveImportedComponents($root, $content);
            $composablePaths = $this->resolveImportedComposables($root, $content);
            $sources = array_merge(
                [['path' => $rel, 'content' => $content]],
                $componentPaths,
                $composablePaths
            );

            $widgets = [];
            foreach ($sources as $source) {
                $widgets = array_merge(
                    $widgets,
                    $this->extractWidgetsFromVue(
                        $root,
                        (string) $source['path'],
                        (string) $source['content']
                    )
                );
            }

            $widgets = $this->uniqueWidgets($widgets);
            if (count($widgets) === 0) {
                continue;
            }

            $stats['pages_with_widgets']++;
            $stats['widgets'] += count($widgets);
            foreach ($widgets as $w) {
                if (($w['tipo'] ?? '') === 'tabla') {
                    $stats['datatables']++;
                }
                if (($w['tipo'] ?? '') === 'filtros') {
                    $stats['filters']++;
                }
            }

            $pageKey = $this->pageKeyFromPath($rel);
            $catalog[] = [
                'key' => $pageKey,
                'label' => $this->pageLabelFromPath($rel),
                'page_path' => str_replace('\\', '/', $rel),
                'widgets' => $widgets,
            ];
        }

        usort($catalog, fn ($a, $b) => strcmp($a['key'], $b['key']));

        return ['pages' => $catalog, 'stats' => $stats, 'front_path' => $root];
    }

    /**
     * Escribe config/manual_usuario_page_widgets.php
     *
     * @param  array<int, array<string, mixed>>  $pages
     */
    public function writeConfig(array $pages, bool $backup = true): string
    {
        $target = config_path('manual_usuario_page_widgets.php');
        if ($backup && is_file($target)) {
            $bak = $target . '.bak.' . date('YmdHis');
            @copy($target, $bak);
        }

        $export = var_export($pages, true);
        $php = "<?php\n\n/**\n * Generado por: php artisan manual:scan-front-widgets --write\n * No editar a mano si vas a regenerar; preferí ajustar el scanner o merge manual.\n */\nreturn {$export};\n";

        file_put_contents($target, $php);

        return $target;
    }

    /**
     * Fusiona pages escaneadas (p. ej. con --only) sobre el catálogo existente.
     *
     * @param  array<int, array<string, mixed>>  $scannedPages
     * @return array<int, array<string, mixed>>
     */
    public function mergeIntoExistingConfig(array $scannedPages): array
    {
        $target = config_path('manual_usuario_page_widgets.php');
        $existing = [];
        if (is_file($target)) {
            $loaded = include $target;
            if (is_array($loaded)) {
                $existing = $loaded;
            }
        }

        $byKey = [];
        foreach ($existing as $page) {
            if (!is_array($page) || empty($page['key'])) {
                continue;
            }
            $byKey[(string) $page['key']] = $page;
        }
        foreach ($scannedPages as $page) {
            if (!is_array($page) || empty($page['key'])) {
                continue;
            }
            $byKey[(string) $page['key']] = $page;
        }

        $merged = array_values($byKey);
        usort($merged, fn ($a, $b) => strcmp((string) ($a['key'] ?? ''), (string) ($b['key'] ?? '')));

        return $merged;
    }

    /**
     * @return array<int, string>
     */
    private function listVueFiles(string $dir): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && strtolower($file->getExtension()) === 'vue') {
                $out[] = $file->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    private function relativePath(string $root, string $absolute): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $abs = str_replace('\\', '/', $absolute);
        if (str_starts_with($abs, $root)) {
            return substr($abs, strlen($root));
        }

        return $abs;
    }

    /**
     * @return array<int, array{path: string, content: string}>
     */
    private function resolveImportedComponents(string $root, string $pageContent): array
    {
        $found = [];
        // createLazyView(() => import('~/components/...'))
        if (preg_match_all("/import\\(['\"]~\\/(components\\/[^'\"]+)['\"]\\)/", $pageContent, $m)) {
            foreach ($m[1] as $rel) {
                $this->pushComponentFile($root, $rel, $found);
            }
        }
        // import X from '~/components/...'
        if (preg_match_all("/from\\s+['\"]~\\/(components\\/[^'\"]+)['\"]/", $pageContent, $m2)) {
            foreach ($m2[1] as $rel) {
                $this->pushComponentFile($root, $rel, $found);
            }
        }

        return array_values($found);
    }

    /**
     * @return array<int, array{path: string, content: string}>
     */
    private function resolveImportedComposables(string $root, string $pageContent): array
    {
        $found = [];
        if (preg_match_all("/from\\s+['\"]~\\/(composables\\/[^'\"]+)['\"]/", $pageContent, $m)) {
            foreach ($m[1] as $rel) {
                $file = $this->resolveFrontFile($root, $rel);
                if (!$file) {
                    continue;
                }
                $key = $file['path'];
                if (isset($found[$key])) {
                    continue;
                }
                $found[$key] = $file;
            }
        }

        return array_values($found);
    }

    /**
     * @param  array<string, array{path: string, content: string}>  $found
     */
    private function pushComponentFile(string $root, string $relImport, array &$found): void
    {
        $rel = str_replace('\\', '/', $relImport);
        $candidates = [
            $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel),
            $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel) . '.vue',
            $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel) . DIRECTORY_SEPARATOR . 'index.vue',
        ];
        foreach ($candidates as $cand) {
            if (!is_file($cand)) {
                continue;
            }
            $key = str_replace('\\', '/', $cand);
            if (isset($found[$key])) {
                return;
            }
            $content = @file_get_contents($cand);
            if ($content === false) {
                return;
            }
            $found[$key] = [
                'path' => $this->relativePath($root, $cand),
                'content' => $content,
            ];

            return;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractWidgetsFromVue(string $root, string $relPath, string $content): array
    {
        $widgets = [];
        $component = str_replace('\\', '/', preg_replace('/\\.vue$/', '', $relPath) ?? $relPath);

        // DataTables + UTable
        foreach (['DataTable', 'UTable'] as $tag) {
            if (!preg_match_all('/<' . $tag . '\\b([^>]*)>/is', $content, $tables, PREG_SET_ORDER)) {
                continue;
            }
            $i = 0;
            foreach ($tables as $match) {
                $i++;
                $attrs = $match[1];
                $title = $this->inferDataTableTitle($attrs, $i);
                if ($title === 'Tabla ' . $i && $tag === 'UTable') {
                    $title = $this->pageLabelHint($relPath) ?: $title;
                }
                $key = 'tabla-' . Str::slug($title !== '' ? $title : ('dt-' . $i));
                $filters = $this->guessFiltersNearTable($content, $attrs);
                $columns = $this->extractColumnDefs($content, $attrs);
                $liveApi = $this->inferLiveApi($root, $content, $attrs);
                if ($liveApi) {
                    $liveApi['kind'] = 'list';
                }
                $roleRules = $this->extractRoleColumnRules($content);
                $widgets[] = [
                    'key' => $key,
                    'label' => 'Tabla — ' . $title,
                    'tipo' => 'tabla',
                    'component' => $component,
                    'api_hint' => $this->inferDataTableHint($attrs),
                    'live_api' => $liveApi,
                    'snapshot' => [
                        'columns' => $columns,
                        'filters' => $filters,
                        'rows' => [],
                        'live_api' => $liveApi,
                        'role_column_rules' => $roleRules,
                    ],
                ];
            }
        }

        // filterConfig* arrays standalone → widget filtros
        foreach ($this->extractFilterConfigs($content) as $fc) {
            $liveApi = $this->inferFilterLiveApi($root, $content);
            if ($liveApi) {
                $liveApi['kind'] = 'filter_options';
            }
            $widgets[] = [
                'key' => 'filtros-' . Str::slug($fc['name']),
                'label' => 'Filtros — ' . $this->humanizeFilterName($fc['name']),
                'tipo' => 'filtros',
                'component' => $component,
                'api_hint' => $fc['name'],
                'live_api' => $liveApi,
                'snapshot' => [
                    'fields' => $fc['fields'],
                    'live_api' => $liveApi,
                ],
            ];
        }

        // Modales UModal
        foreach ($this->extractModals($content) as $idx => $modal) {
            $widgets[] = [
                'key' => 'modal-' . Str::slug($modal['title'] ?: ('modal-' . ($idx + 1))),
                'label' => 'Modal — ' . ($modal['title'] ?: ('Modal ' . ($idx + 1))),
                'tipo' => 'modal',
                'component' => $component,
                'api_hint' => null,
                'live_api' => $modal['live_api'] ?? null,
                'snapshot' => [
                    'title' => $modal['title'],
                    'fields' => $modal['fields'],
                    'actions' => $modal['actions'],
                    'live_api' => $modal['live_api'] ?? null,
                ],
            ];
        }

        // Tabs (UTabs / const tabs = [...])
        $tabItems = $this->extractTabItems($content);
        if (count($tabItems) >= 2) {
            $widgets[] = [
                'key' => 'tabs-' . Str::slug(implode('-', array_slice(array_column($tabItems, 'label'), 0, 3))),
                'label' => 'Tabs — ' . implode(' / ', array_slice(array_column($tabItems, 'label'), 0, 4)),
                'tipo' => 'tabs',
                'component' => $component,
                'api_hint' => null,
                'live_api' => null,
                'snapshot' => [
                    'active' => $tabItems[0]['key'],
                    'tabs' => $tabItems,
                ],
            ];
        }

        // Toolbar: bloque completo + cada acción por separado
        foreach ($this->extractToolbars($content) as $tb) {
            $widgets[] = [
                'key' => $tb['key'],
                'label' => $tb['label'],
                'tipo' => 'toolbar',
                'component' => $component,
                'api_hint' => $tb['hint'] ?? null,
                'live_api' => null,
                'snapshot' => [
                    'buttons' => $tb['buttons'],
                ],
            ];

            foreach ($tb['buttons'] as $btn) {
                $label = (string) ($btn['label'] ?? 'Acción');
                $slug = Str::slug($label) ?: 'accion';
                $widgets[] = [
                    'key' => 'accion-' . $slug,
                    'label' => 'Acción — ' . $label,
                    'tipo' => 'accion',
                    'component' => $component,
                    'api_hint' => $tb['hint'] ?? null,
                    'live_api' => null,
                    'snapshot' => [
                        'label' => $label,
                        'icon' => $btn['icon'] ?? null,
                        'color' => $btn['color'] ?? 'primary',
                        'variant' => $btn['variant'] ?? 'solid',
                    ],
                ];
            }
        }

        // UCard (fuera de UModal; los de modal ya van en widget modal)
        foreach ($this->extractCards($content) as $idx => $card) {
            $widgets[] = [
                'key' => 'card-' . Str::slug($card['title'] !== '' ? $card['title'] : ('card-' . ($idx + 1))),
                'label' => 'Card — ' . ($card['title'] !== '' ? $card['title'] : ('Card ' . ($idx + 1))),
                'tipo' => 'card',
                'component' => $component,
                'api_hint' => $card['hint'] ?? null,
                'live_api' => null,
                'snapshot' => [
                    'title' => $card['title'],
                    'icon' => $card['icon'],
                    'body' => $card['body'],
                    'fields' => $card['fields'],
                    'buttons' => $card['buttons'],
                ],
            ];
        }

        return $widgets;
    }

    /**
     * @return array<int, array{title: string, icon: ?string, body: string, fields: array, buttons: array, hint?: string}>
     */
    private function extractCards(string $content): array
    {
        // Evitar UCards dentro de modales (ya se capturan como modal)
        $scoped = preg_replace('/<UModal\\b[\\s\\S]*?<\\/UModal>/i', '', $content) ?? $content;
        $out = [];

        if (!preg_match_all('/<UCard\\b([^>]*)>([\\s\\S]*?)<\\/UCard>/i', $scoped, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $i => $m) {
            $inner = $m[2];
            // Saltar skeletons / cards vacías de loading
            if (preg_match('/<USkeleton\\b/i', $inner) && !preg_match('/<U(Button|Input|Select|FormField|Table|Icon)\\b/i', $inner)) {
                continue;
            }

            $title = '';
            if (preg_match('/<template\\s+#header>([\\s\\S]*?)<\\/template>/i', $inner, $hdr)) {
                if (preg_match('/<h[1-4][^>]*>\\s*([\\s\\S]*?)\\s*<\\/h[1-4]>/i', $hdr[1], $hm)) {
                    $title = trim(html_entity_decode(strip_tags($hm[1])));
                    // Quitar interpolaciones crudas tipo {{ x }}
                    $title = trim(preg_replace('/\\{\\{[^}]+\\}\\}/', '', $title) ?? $title);
                }
                if ($title === '' && preg_match('/>([^<]{2,80})</', $hdr[1], $tm)) {
                    $title = trim($tm[1]);
                }
            }

            $icon = null;
            if (preg_match('/<UIcon\\b[^>]*name=["\']([^"\']+)["\']/i', $inner, $im)) {
                $icon = $im[1];
            }

            $buttons = $this->extractUButtonsFromHtml($inner);
            $fields = [];
            if (preg_match_all('/<UFormField\\b([^>]*)>/i', $inner, $ffs, PREG_SET_ORDER)) {
                foreach ($ffs as $ff) {
                    $label = $this->attrString($ff[1], 'label') ?: 'Campo';
                    $fields[] = [
                        'key' => Str::slug($label),
                        'label' => $label,
                        'type' => 'text',
                        'value' => '',
                        'options' => [],
                    ];
                }
            }
            // labels sueltos + input
            if (!$fields && preg_match_all('/<label\\b[^>]*>\\s*([^<]{2,60})\\s*<\\/label>/i', $inner, $labs)) {
                foreach ($labs[1] as $lab) {
                    $label = trim(html_entity_decode(strip_tags($lab)));
                    if ($label === '') {
                        continue;
                    }
                    $fields[] = [
                        'key' => Str::slug($label),
                        'label' => $label,
                        'type' => 'text',
                        'value' => '',
                        'options' => [],
                    ];
                    if (count($fields) >= 8) {
                        break;
                    }
                }
            }

            $body = '';
            if (preg_match('/<(?:p|span|div)[^>]*class="[^"]*(?:text-sm|text-muted|description)[^"]*"[^>]*>\\s*([^<]{5,160})/i', $inner, $bm)) {
                $body = trim(html_entity_decode(strip_tags($bm[1])));
            }

            $hint = null;
            if (preg_match('/<(?:UTable|DataTable)\\b/i', $inner)) {
                $hint = 'contiene tabla';
                if ($title === '') {
                    $title = 'Tabla';
                }
            }

            if ($title === '' && !$buttons && !$fields && $body === '') {
                $title = 'Card ' . ($i + 1);
            }

            $out[] = [
                'title' => $title !== '' ? $title : ('Card ' . ($i + 1)),
                'icon' => $icon,
                'body' => $body,
                'fields' => $fields,
                'buttons' => $buttons,
                'hint' => $hint,
            ];
        }

        return $out;
    }

    /**
     * Toolbars: slot #actions, botones de cabecera y acciones de fila (iconos).
     *
     * @return array<int, array{key: string, label: string, buttons: array<int, array<string, mixed>>, hint?: string}>
     */
    private function extractToolbars(string $content): array
    {
        $out = [];

        // <template #actions>...</template>
        if (preg_match_all('/<template\\s+#actions>([\\s\\S]*?)<\\/template>/i', $content, $slots)) {
            $seenSignatures = [];
            foreach ($slots[1] as $idx => $slot) {
                $buttons = $this->extractUButtonsFromHtml($slot);
                if (!$buttons) {
                    continue;
                }
                $sig = implode('|', array_map(
                    fn ($b) => ($b['icon'] ?? '') . ':' . ($b['label'] ?? ''),
                    $buttons
                ));
                if (isset($seenSignatures[$sig])) {
                    continue;
                }
                $seenSignatures[$sig] = true;
                $out[] = [
                    'key' => 'toolbar-acciones' . (count($seenSignatures) > 1 ? '-' . count($seenSignatures) : ''),
                    'label' => 'Toolbar — Acciones' . (count($seenSignatures) > 1 ? ' ' . count($seenSignatures) : ''),
                    'buttons' => $buttons,
                    'hint' => '#actions',
                ];
            }
        }

        // Acciones de fila: h(UButton, { icon: 'i-heroicons-eye', ... })
        $rowButtons = $this->extractRowActionButtons($content);
        if ($rowButtons) {
            $out[] = [
                'key' => 'toolbar-acciones-fila',
                'label' => 'Toolbar — Acciones de fila',
                'buttons' => $rowButtons,
                'hint' => 'column:acciones',
            ];
        }

        // Chrome DataTable (export / filtros / nuevo) si aparecen en attrs
        $chrome = $this->extractDataTableChromeButtons($content);
        if ($chrome) {
            $out[] = [
                'key' => 'toolbar-datatable',
                'label' => 'Toolbar — Controles de tabla',
                'buttons' => $chrome,
                'hint' => 'DataTable chrome',
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{label: string, icon: ?string, color: string, variant: string}>
     */
    private function extractUButtonsFromHtml(string $block): array
    {
        $buttons = [];
        if (!preg_match_all('/<UButton\\b([^>]*?)(?:\\/>|>([\\s\\S]*?)<\\/UButton>)/i', $block, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $m) {
            $attrs = $m[1];
            $inner = trim(html_entity_decode(strip_tags($m[2] ?? '')));
            $label = $this->attrString($attrs, 'label');
            if ($label === null || $label === '') {
                $label = $inner !== '' ? $inner : $this->labelFromIcon($this->attrString($attrs, 'icon'));
            }
            $icon = $this->attrString($attrs, 'icon');
            if (($label === null || $label === '') && !$icon) {
                continue;
            }
            $buttons[] = [
                'label' => $label !== null && $label !== '' ? $label : 'Acción',
                'icon' => $icon,
                'color' => $this->attrString($attrs, 'color') ?: 'primary',
                'variant' => $this->attrString($attrs, 'variant') ?: 'solid',
            ];
        }

        return $this->uniqueToolbarButtons($buttons);
    }

    /**
     * @return array<int, array{label: string, icon: ?string, color: string, variant: string}>
     */
    private function extractRowActionButtons(string $content): array
    {
        $buttons = [];
        $offset = 0;
        while (preg_match('/h\\(\\s*UButton\\s*,\\s*\\{/', $content, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1] + strlen($m[0][0]);
            $depth = 1;
            $len = strlen($content);
            $end = $start;
            for ($i = $start; $i < $len && $i < $start + 1200; $i++) {
                $ch = $content[$i];
                if ($ch === '{') {
                    $depth++;
                } elseif ($ch === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $end = $i;
                        break;
                    }
                }
            }
            if ($depth !== 0) {
                $offset = $start;
                continue;
            }
            $props = substr($content, $start, $end - $start);
            $offset = $end + 1;

            $icon = null;
            $variant = 'outline';
            $color = 'primary';
            if (preg_match('/icon\\s*:\\s*[\'"]([^\'"]+)[\'"]/', $props, $im)) {
                $icon = $im[1];
            }
            if (preg_match('/variant\\s*:\\s*[\'"]([^\'"]+)[\'"]/', $props, $vm)) {
                $variant = $vm[1];
            }
            if (preg_match('/color\\s*:\\s*[\'"]([^\'"]+)[\'"]/', $props, $cm)) {
                $color = $cm[1];
            }
            if (!$icon) {
                continue;
            }
            $buttons[] = [
                'label' => $this->labelFromIcon($icon),
                'icon' => $icon,
                'color' => $color,
                'variant' => $variant,
            ];
        }

        return $this->uniqueToolbarButtons($buttons);
    }

    /**
     * @return array<int, array{label: string, icon: ?string, color: string, variant: string}>
     */
    private function extractDataTableChromeButtons(string $content): array
    {
        $buttons = [];

        // show-export presente y no explícitamente false
        if (preg_match('/:?show-export\\s*=\\s*["\'](?!false\\b)[^"\']*["\']/i', $content)) {
            $buttons[] = [
                'label' => 'Exportar',
                'icon' => 'i-heroicons-arrow-up-tray',
                'color' => 'neutral',
                'variant' => 'outline',
            ];
        }

        if (preg_match('/:?show-filters\\s*=\\s*["\'](?!false\\b)[^"\']*["\']/i', $content)) {
            $buttons[] = [
                'label' => 'Filtros',
                'icon' => 'i-heroicons-funnel',
                'color' => 'neutral',
                'variant' => 'outline',
            ];
        }

        if (preg_match('/:?show-new(?:-button)?\\s*=\\s*["\'](?!false\\b)[^"\']*["\']/i', $content)
            || preg_match('/\\bnew-button-label\\s*=/i', $content)
            || preg_match('/@new(?:-click)?\\b/i', $content)) {
            $buttons[] = [
                'label' => 'Nuevo',
                'icon' => 'i-heroicons-plus',
                'color' => 'primary',
                'variant' => 'solid',
            ];
        }

        if (preg_match('/:?show-primary-search\\s*=\\s*["\'](?!false\\b)[^"\']*["\']/i', $content)) {
            $buttons[] = [
                'label' => 'Buscar',
                'icon' => 'i-heroicons-magnifying-glass',
                'color' => 'neutral',
                'variant' => 'outline',
            ];
        }

        return $this->uniqueToolbarButtons($buttons);
    }

    private function labelFromIcon(?string $icon): string
    {
        if ($icon === null || $icon === '') {
            return 'Acción';
        }
        $i = strtolower($icon);
        return match (true) {
            str_contains($i, 'eye') || str_contains($i, 'view') => 'Ver',
            str_contains($i, 'trash') || str_contains($i, 'delete') => 'Eliminar',
            str_contains($i, 'pencil') || str_contains($i, 'edit') => 'Editar',
            str_contains($i, 'plus') || str_contains($i, 'add') => 'Agregar',
            str_contains($i, 'save') => 'Guardar',
            str_contains($i, 'chat') || str_contains($i, 'whatsapp') || str_contains($i, 'bubble') => 'Mensaje',
            str_contains($i, 'download') || str_contains($i, 'arrow-down-tray') => 'Descargar',
            str_contains($i, 'upload') || str_contains($i, 'arrow-up-tray') => 'Exportar',
            str_contains($i, 'funnel') || str_contains($i, 'filter') => 'Filtros',
            str_contains($i, 'globe') => 'Web',
            str_contains($i, 'printer') || str_contains($i, 'print') => 'Imprimir',
            str_contains($i, 'cog') || str_contains($i, 'settings') => 'Configurar',
            default => 'Acción',
        };
    }

    /**
     * @param  array<int, array{label: string, icon: ?string, color: string, variant: string}>  $buttons
     * @return array<int, array{label: string, icon: ?string, color: string, variant: string}>
     */
    private function uniqueToolbarButtons(array $buttons): array
    {
        $seen = [];
        $out = [];
        foreach ($buttons as $b) {
            $k = ($b['icon'] ?? '') . '|' . ($b['label'] ?? '');
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $b;
        }

        return $out;
    }

    private function attrString(string $attrs, string $name): ?string
    {
        if (preg_match('/\\b' . preg_quote($name, '/') . '\\s*=\\s*"([^"]*)"/', $attrs, $m)) {
            return $m[1];
        }
        if (preg_match("/\\b" . preg_quote($name, '/') . "\\s*=\\s*'([^']*)'/", $attrs, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Título útil cuando title="" (p. ej. Curso: Alumnos / Pagos vía activeTab o :data).
     */
    private function inferDataTableTitle(string $attrs, int $index): string
    {
        $title = $this->attrString($attrs, 'title');
        if ($title !== null && trim($title) !== '') {
            return trim($title);
        }

        // v-if="activeTab === 'alumnos'" / tab === "pagos"
        if (preg_match(
            "/(?:v-if|v-else-if|v-show)\\s*=\\s*[\"'][^\"']*(?:activeTab|tab|currentTab)\\s*===?\\s*['\"]([^'\"]+)['\"]/i",
            $attrs,
            $m
        )) {
            return $this->humanizeIdentifier($m[1]);
        }

        // :columns="getAlumnosColumns()" | columnsPagos | alumnosColumns
        if (preg_match('/:columns\\s*=\\s*["\']([^"\']+)["\']/', $attrs, $m)) {
            $ref = trim($m[1]);
            if (preg_match('/get([A-Za-z0-9_]+)Columns\\s*\\(/', $ref, $g)) {
                return $this->humanizeIdentifier($g[1]);
            }
            if (preg_match('/\\bcolumns([A-Za-z0-9_]+)\\b/', $ref, $g)) {
                return $this->humanizeIdentifier($g[1]);
            }
            if (preg_match('/\\b([A-Za-z0-9_]+)Columns\\b/', $ref, $g)) {
                return $this->humanizeIdentifier($g[1]);
            }
        }

        // :data="pagosData" | cursosData | alumnos
        if (preg_match('/:data\\s*=\\s*["\']([^"\']+)["\']/', $attrs, $m)) {
            $ref = preg_replace('/\\.value$/', '', trim($m[1])) ?? trim($m[1]);
            $ref = preg_replace('/Data$/i', '', $ref) ?? $ref;
            $ref = preg_replace('/List$/i', '', $ref) ?? $ref;
            $ref = preg_replace('/Items$/i', '', $ref) ?? $ref;
            if ($ref !== '' && !in_array(strtolower($ref), ['data', 'items', 'rows', 'list', 'table'], true)) {
                return $this->humanizeIdentifier($ref);
            }
        }

        return 'Tabla ' . $index;
    }

    private function inferDataTableHint(string $attrs): ?string
    {
        $parts = [];
        if (preg_match('/:data\\s*=\\s*["\']([^"\']+)["\']/', $attrs, $m)) {
            $parts[] = 'data:' . trim($m[1]);
        }
        if (preg_match('/:columns\\s*=\\s*["\']([^"\']+)["\']/', $attrs, $m)) {
            $parts[] = 'columns:' . trim($m[1]);
        }
        if (preg_match(
            "/(?:v-if|v-else-if)\\s*=\\s*[\"'][^\"']*(?:activeTab|tab)\\s*===?\\s*['\"]([^'\"]+)['\"]/i",
            $attrs,
            $m
        )) {
            $parts[] = 'tab:' . $m[1];
        }

        return $parts ? implode(' · ', $parts) : null;
    }

    private function humanizeIdentifier(string $id): string
    {
        $id = trim($id);
        $id = str_replace(['_', '-'], ' ', $id);
        $id = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $id) ?? $id;

        return Str::title(trim($id));
    }

    /**
     * @return array<int, string>
     */
    private function guessColumns(string $content, string $attrs, string $title): array
    {
        $defs = $this->extractColumnDefs($content, $attrs);
        if (count($defs) > 0) {
            return array_map(fn ($c) => $c['header'], $defs);
        }

        return [$title ?: 'Columna', 'Estado', 'Acciones'];
    }

    /**
     * Extrae { accessorKey, header } del array de columnas referenciado por el DataTable.
     *
     * @return array<int, array{accessorKey: string, header: string}>
     */
    private function extractColumnDefs(string $content, string $attrs): array
    {
        $candidates = [];
        if (preg_match('/:columns\\s*=\\s*["\']([^"\']+)["\']/', $attrs, $m)) {
            $ref = trim($m[1]);
            if (preg_match('/get([A-Za-z0-9_]+)Columns\\s*\\(/', $ref, $g)) {
                $getter = 'get' . $g[1] . 'Columns';
                // Seguir return columns.value / return columnsXxx.value del getter
                foreach ($this->resolveColumnSourceNames($content, $getter) as $src) {
                    $candidates[] = $src;
                }
                $candidates[] = 'columns';
                $candidates[] = 'columns' . $g[1];
                $candidates[] = $getter;
            } elseif (preg_match('/\\b([A-Za-z0-9_]+)\\b/', $ref, $g)) {
                $candidates[] = $g[1];
            }
        }

        $candidates = array_values(array_unique(array_filter($candidates)));
        $best = [];
        foreach ($candidates as $name) {
            $cols = $this->parseColumnObjectsNearName($content, $name);
            if (count($cols) > count($best)) {
                $best = $cols;
            }
            // Preferir el primer match “rico” (>= 3 cols con tipos UI)
            $rich = 0;
            foreach ($cols as $c) {
                if (in_array(($c['type'] ?? 'text'), ['select', 'multiline', 'currency', 'buttons', 'input'], true)) {
                    $rich++;
                }
            }
            if (count($cols) >= 4 && $rich >= 2) {
                return $cols;
            }
        }
        if (count($best) > 0) {
            return $best;
        }

        // Fallback: primer bloque grande de accessorKey/header en el archivo
        $cols = $this->parseColumnObjectsInSlice($content);
        if (count($cols) >= 2) {
            return array_slice($cols, 0, 16);
        }

        return [
            ['accessorKey' => 'c0', 'header' => 'Columna 1'],
            ['accessorKey' => 'c1', 'header' => 'Columna 2'],
            ['accessorKey' => 'c2', 'header' => 'Columna 3'],
        ];
    }

    /**
     * Nombres de arrays/refs que realmente definen columnas (p. ej. getAlumnosColumns → columns).
     *
     * @return array<int, string>
     */
    private function resolveColumnSourceNames(string $content, string $getterName): array
    {
        $names = [];
        if (!preg_match(
            '/(?:const|let)\\s+' . preg_quote($getterName, '/') . '\\s*=\\s*(?:async\\s*)?\\([^)]*\\)\\s*(?::\\s*[^=]+)?\\s*=>/',
            $content,
            $m,
            PREG_OFFSET_CAPTURE
        ) && !preg_match(
            '/function\\s+' . preg_quote($getterName, '/') . '\\s*\\(/',
            $content,
            $m,
            PREG_OFFSET_CAPTURE
        )) {
            return $names;
        }

        $slice = substr($content, $m[0][1], 4000);
        if (preg_match_all('/return\\s+([A-Za-z0-9_]+)(?:\\.value)?\\b/', $slice, $rm)) {
            foreach ($rm[1] as $id) {
                if (!preg_match('/^(true|false|null|undefined|column)$/i', $id)) {
                    $names[] = $id;
                }
            }
        }

        return $names;
    }

    /**
     * @return array<int, array{accessorKey: string, header: string}>
     */
    private function parseColumnObjectsNearName(string $content, string $name): array
    {
        // ref<TableColumn<CursoItem>[]>([  — genéricos anidados
        $patterns = [
            '/(?:const|let)\\s+' . preg_quote($name, '/') . '\\s*=\\s*ref\\b/',
            '/(?:const|let)\\s+' . preg_quote($name, '/') . '\\s*=\\s*\\[/',
            '/(?:const|let)\\s+' . preg_quote($name, '/') . '\\s*=\\s*(?:async\\s*)?\\([^)]*\\)\\s*(?::\\s*[^=]+)?\\s*=>/',
            '/function\\s+' . preg_quote($name, '/') . '\\s*\\(/',
            '/(?:const|let)\\s+' . preg_quote($name, '/') . '\\s*=\\s*(?:async\\s*)?function/',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $start = $m[0][1];
            $window = substr($content, $start, 60000);
            $arrayBody = null;
            if (str_contains($m[0][0], 'ref')) {
                $arrayBody = $this->extractRefArrayBody($window);
            }
            if ($arrayBody === null) {
                $arrayBody = $this->extractFirstValueArrayBody($window);
            }
            $slice = $arrayBody !== null ? $arrayBody : substr($window, 0, 25000);
            $cols = $this->parseColumnObjectsInSlice($slice);
            if (count($cols) > 0) {
                return $cols;
            }
        }

        return [];
    }

    /**
     * Cuerpo del array pasado a ref([...]) ignorando genéricos TS anidados.
     */
    private function extractRefArrayBody(string $window): ?string
    {
        if (!preg_match('/\\bref\\b/', $window, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $i = $m[0][1] + strlen('ref');
        $len = strlen($window);
        while ($i < $len && ctype_space($window[$i])) {
            $i++;
        }
        if ($i < $len && $window[$i] === '<') {
            $depth = 0;
            for (; $i < $len; $i++) {
                $ch = $window[$i];
                if ($ch === '<') {
                    $depth++;
                } elseif ($ch === '>') {
                    $depth--;
                    if ($depth === 0) {
                        $i++;
                        break;
                    }
                }
            }
        }
        while ($i < $len && ctype_space($window[$i])) {
            $i++;
        }
        if ($i >= $len || $window[$i] !== '(') {
            return null;
        }
        $i++;
        while ($i < $len && ctype_space($window[$i])) {
            $i++;
        }
        if ($i >= $len || $window[$i] !== '[') {
            return null;
        }

        return $this->extractArrayBodyAt($window, $i);
    }

    /**
     * Primer array de valor tras `= [` (no el `[]` de un tipo TS).
     */
    private function extractFirstValueArrayBody(string $window): ?string
    {
        if (preg_match('/=\\s*\\[/', $window, $m, PREG_OFFSET_CAPTURE)) {
            $bracket = $m[0][1] + strlen($m[0][0]) - 1;

            return $this->extractArrayBodyAt($window, $bracket);
        }
        // Función getter: buscar return [ o primer [ significativo más adelante
        if (preg_match('/\\[[\\s\\n]*\\{/', $window, $m, PREG_OFFSET_CAPTURE)) {
            return $this->extractArrayBodyAt($window, $m[0][1]);
        }

        return $this->extractFirstArrayBody($window);
    }

    /**
     * Cuerpo del array `[...]` empezando en la posición del `[`.
     */
    private function extractArrayBodyAt(string $slice, int $start): ?string
    {
        if (!isset($slice[$start]) || $slice[$start] !== '[') {
            return null;
        }
        $depth = 0;
        $len = strlen($slice);
        $inStr = null;
        $escape = false;
        for ($i = $start; $i < $len; $i++) {
            $ch = $slice[$i];
            if ($inStr !== null) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === $inStr) {
                    $inStr = null;
                }
                continue;
            }
            if ($ch === '"' || $ch === "'" || $ch === '`') {
                $inStr = $ch;
                continue;
            }
            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($slice, $start + 1, $i - $start - 1);
                }
            }
        }

        return null;
    }

    /**
     * Cuerpo del primer array `[...]` con brackets balanceados (sin incluir los corchetes).
     */
    private function extractFirstArrayBody(string $slice): ?string
    {
        $start = strpos($slice, '[');
        if ($start === false) {
            return null;
        }

        return $this->extractArrayBodyAt($slice, $start);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseColumnObjectsInSlice(string $slice): array
    {
        $cols = [];
        $offset = 0;
        $len = strlen($slice);
        while ($offset < $len && count($cols) < 20) {
            if (!preg_match('/\\{[\\s\\n]*accessorKey\\s*:/', $slice, $m, PREG_OFFSET_CAPTURE, $offset)
                && !preg_match('/\\{[\\s\\n]*header\\s*:/', $slice, $m, PREG_OFFSET_CAPTURE, $offset)) {
                break;
            }
            $braceStart = $m[0][1];
            $obj = $this->extractBalancedBraceObject($slice, $braceStart);
            if ($obj === null) {
                $offset = $braceStart + 1;
                continue;
            }
            $offset = $braceStart + strlen($obj);

            $accessorKey = null;
            $header = null;
            if (preg_match('/accessorKey\\s*:\\s*[\'"]([^\'"]+)[\'"]/', $obj, $am)) {
                $accessorKey = $am[1];
            }
            if (preg_match('/header\\s*:\\s*[\'"]([^\'"]+)[\'"]/', $obj, $hm)) {
                $header = $hm[1];
            }
            if ($accessorKey === null || $header === null) {
                continue;
            }
            $cols[] = $this->enrichColumnDef($accessorKey, $header, $obj);
        }

        if ($cols !== []) {
            return $cols;
        }

        // Fallback regex (páginas con columnas muy simples)
        if (preg_match_all(
            '/accessorKey\\s*:\\s*[\'"]([^\'"]+)[\'"][\\s\\S]{0,240}?header\\s*:\\s*[\'"]([^\'"]+)[\'"]([\\s\\S]{0,2800}?)(?=\\n\\s*\\{|,?\\s*\\n\\s*\\])/u',
            $slice,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $cols[] = $this->enrichColumnDef($m[1], $m[2], $m[3] ?? '');
                if (count($cols) >= 16) {
                    break;
                }
            }
        }

        return $cols;
    }

    /**
     * Extrae un objeto `{...}` con llaves balanceadas desde $start (posición de `{`).
     */
    private function extractBalancedBraceObject(string $src, int $start): ?string
    {
        if (!isset($src[$start]) || $src[$start] !== '{') {
            return null;
        }
        $depth = 0;
        $len = strlen($src);
        $inStr = null;
        $escape = false;
        for ($i = $start; $i < $len; $i++) {
            $ch = $src[$i];
            if ($inStr !== null) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === $inStr) {
                    $inStr = null;
                }
                continue;
            }
            if ($ch === '"' || $ch === "'" || $ch === '`') {
                $inStr = $ch;
                continue;
            }
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($src, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichColumnDef(string $accessorKey, string $header, string $body): array
    {
        $col = [
            'accessorKey' => $accessorKey,
            'header' => $header,
            'type' => 'text',
        ];

        $readonlyRoles = [];
        if (preg_match('/disabled\\s*:\\s*isJefeMarketing/i', $body)
            || preg_match('/ROLES\\.JEFE_MARKETING/i', $body) && preg_match('/disabled\\s*:/i', $body)) {
            $readonlyRoles[] = 'jefe-marketing';
        }
        if ($readonlyRoles) {
            $col['readonly_roles'] = array_values(array_unique($readonlyRoles));
        }

        if (preg_match('/h\\(\\s*USelect\\b/i', $body)) {
            $col['type'] = 'select';
            $col['options'] = $this->parseInlineSelectOptions($body);
            if ($col['options'] === [] && preg_match(
                '/filterConfig[\\s\\S]{0,120}?key\\s*===\\s*[\'"]([^\'"]+)[\'"]/',
                $body,
                $fm
            )) {
                $col['options_from_filter'] = $fm[1];
            }
            if (preg_match('/modelValue\\s*:\\s*row\\.original\\.([A-Za-z0-9_]+)/', $body, $vm)) {
                $col['value_key'] = $vm[1];
            } elseif (preg_match(
                '/[\'"]onUpdate:modelValue[\'"]\\s*:[\\s\\S]{0,120}?row\\.original\\.([A-Za-z0-9_]+)\\s*=/',
                $body,
                $vm
            )) {
                $col['value_key'] = $vm[1];
            } elseif (preg_match('/row\\.original\\.([A-Za-z0-9_]+)/', $body, $vm)) {
                $col['value_key'] = $vm[1];
            }
            // Estado de pago calculado (pendiente/adelanto/pagado/sobrepago)
            $optValues = array_map(fn ($o) => (string) ($o['value'] ?? ''), $col['options']);
            if (count(array_intersect($optValues, ['pendiente', 'adelanto', 'pagado', 'sobrepago'])) >= 3) {
                $col['compute'] = 'pago_estado';
                $col['value_key'] = 'estado_pago';
                $col['readonly'] = true;
            }
            // disabled: true en props del select (antes de items), no en opciones internas
            if (preg_match('/h\\(\\s*USelect\\b([\\s\\S]*?)\\bitems\\s*:/i', $body, $sm)
                && preg_match('/disabled\\s*:\\s*true\\b/', $sm[1])) {
                $col['readonly'] = true;
            }
        } elseif (preg_match('/formatCurrency\\s*\\(/i', $body)) {
            $col['type'] = 'currency';
            $col['currency'] = preg_match('/[\'"]USD[\'"]/', $body) ? 'USD' : 'PEN';
            if (preg_match('/row\\.original\\.([A-Za-z0-9_]+)/', $body, $vm)) {
                $col['value_key'] = $vm[1];
            }
        } elseif (preg_match('/h\\(\\s*[\'"]div[\'"][\\s\\S]{0,200}?h\\(\\s*[\'"]p[\'"]/i', $body)
            || (substr_count($body, "h('p'") + substr_count($body, 'h("p"') >= 2)) {
            $col['type'] = 'multiline';
            if (preg_match_all('/row\\.original\\.([A-Za-z0-9_]+)/', $body, $fm)) {
                $col['fields'] = array_values(array_unique($fm[1]));
            }
        } elseif (preg_match('/h\\(\\s*UButton\\b/i', $body)
            || preg_match('/^(acciones|actions?)$/i', $accessorKey)
            || preg_match('/^acciones$/i', $header)) {
            $col['type'] = 'buttons';
            $col['buttons'] = $this->extractButtonsFromColumnBody($body);
        } elseif (preg_match('/h\\(\\s*UInput\\b/i', $body)) {
            $col['type'] = 'input';
            if (preg_match('/row\\.original\\.([A-Za-z0-9_]+)/', $body, $vm)) {
                $col['value_key'] = $vm[1];
            }
        }

        return $col;
    }

    /**
     * @return array<int, array{label: string, value: string|int}>
     */
    private function parseInlineSelectOptions(string $body): array
    {
        $options = [];
        // const items = [ { label: 'Virtual', value: "0", ...}, ...]
        if (preg_match('/items\\s*=\\s*\\[([\\s\\S]{0,2000}?)\\]/', $body, $m)
            || preg_match('/items\\s*:\\s*\\[([\\s\\S]{0,2000}?)\\]/', $body, $m)) {
            if (preg_match_all(
                '/label\\s*:\\s*[\'"]([^\'"]+)[\'"][\\s\\S]{0,120}?value\\s*:\\s*[\'"]?([^\'",\\s}]+)[\'"]?/u',
                $m[1],
                $pairs,
                PREG_SET_ORDER
            )) {
                foreach ($pairs as $p) {
                    $options[] = ['label' => $p[1], 'value' => $p[2]];
                }
            }
        }

        return $options;
    }

    /**
     * @return array<int, array{label: string, icon: ?string, color: string, variant: string}>
     */
    private function extractButtonsFromColumnBody(string $body): array
    {
        $buttons = [];
        $offset = 0;
        while (preg_match('/h\\(\\s*UButton\\s*,\\s*\\{/', $body, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1] + strlen($m[0][0]);
            $depth = 1;
            $len = strlen($body);
            $end = $start;
            for ($i = $start; $i < $len && $i < $start + 800; $i++) {
                if ($body[$i] === '{') {
                    $depth++;
                } elseif ($body[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $end = $i;
                        break;
                    }
                }
            }
            if ($depth !== 0) {
                break;
            }
            $props = substr($body, $start, $end - $start);
            $offset = $end + 1;
            $icon = null;
            $variant = 'outline';
            $color = 'primary';
            if (preg_match('/icon\\s*:\\s*[\'"]([^\'"]+)[\'"]/', $props, $im)) {
                $icon = $im[1];
            }
            if (preg_match('/variant\\s*:\\s*[\'"]([^\'"]+)[\'"]/', $props, $vm)) {
                $variant = $vm[1];
            }
            if (preg_match('/color\\s*:\\s*[\'"]([^\'"]+)[\'"]/', $props, $cm)) {
                $color = $cm[1];
            }
            if (!$icon) {
                continue;
            }
            $buttons[] = [
                'label' => $this->labelFromIcon($icon),
                'icon' => $icon,
                'color' => $color,
                'variant' => $variant,
            ];
        }

        return $this->uniqueToolbarButtons($buttons);
    }

    /**
     * Reglas por rol detectadas en getters tipo getAlumnosColumns().
     *
     * @return array<string, array<string, mixed>>
     */
    private function extractRoleColumnRules(string $content): array
    {
        $rules = [];
        // if (isJefeMarketing…) { … solo eye en acciones }
        if (preg_match('/isJefeMarketing[\\s\\S]{0,800}?i-heroicons-eye/i', $content)) {
            $rules['jefe-marketing'] = [
                'readonly' => true,
                'column_overrides' => [
                    'acciones' => [
                        'type' => 'buttons',
                        'buttons' => [[
                            'label' => 'Ver',
                            'icon' => 'i-heroicons-eye',
                            'color' => 'primary',
                            'variant' => 'solid',
                        ]],
                    ],
                ],
            ];
        }

        return $rules;
    }

    /**
     * Intenta resolver API real desde :data + composables/services del front.
     *
     * @return array{path: string, method: string, params: array<string, mixed>, data_key: string}|null
     */
    private function inferLiveApi(string $root, string $content, string $attrs): ?array
    {
        if (!preg_match('/:data\\s*=\\s*["\']([A-Za-z0-9_]+)["\']/', $attrs, $m)) {
            return null;
        }
        $dataVar = $m[1];

        $sources = [$content];
        // composables importados desde la page
        if (preg_match_all("/from\\s+['\"]~\\/(composables\\/[^'\"]+)['\"]/", $content, $cm)) {
            foreach ($cm[1] as $rel) {
                $file = $this->resolveFrontFile($root, $rel);
                if ($file) {
                    $sources[] = $file['content'];
                }
            }
        }

        $serviceClass = null;
        foreach ($sources as $src) {
            // Preferir servicio usado cerca del dataVar
            if (str_contains($src, $dataVar) && preg_match('/([A-Za-z0-9_]+Service)\\.([A-Za-z0-9_]+)/', $src, $sm)) {
                $serviceClass = $sm[1];
                break;
            }
        }
        if (!$serviceClass) {
            foreach ($sources as $src) {
                if (preg_match('/([A-Za-z0-9_]+Service)\\.([A-Za-z0-9_]+)/', $src, $sm)) {
                    // pagosData → PagosService preferido
                    if (stripos($dataVar, 'pago') !== false && stripos($sm[1], 'Pago') !== false) {
                        $serviceClass = $sm[1];
                        break;
                    }
                    if (stripos($dataVar, 'pago') === false && stripos($sm[1], 'Pago') === false) {
                        $serviceClass = $sm[1];
                        break;
                    }
                    $serviceClass = $serviceClass ?: $sm[1];
                }
            }
        }

        if (!$serviceClass) {
            return null;
        }

        $serviceContent = null;
        foreach ($sources as $src) {
            if (preg_match(
                '/import\\s*\\{?[^}]*\\b' . preg_quote($serviceClass, '/') . '\\b[^}]*\\}?\\s*from\\s*[\'"]([^\'"]+)[\'"]/',
                $src,
                $im
            )) {
                $importPath = $im[1];
                $importPath = preg_replace('#^~/#', '', $importPath) ?? $importPath;
                $file = $this->resolveFrontFile($root, $importPath);
                if ($file) {
                    $serviceContent = $file['content'];
                    break;
                }
            }
        }

        if (!$serviceContent) {
            return null;
        }

        if (!preg_match('/baseUrl\\s*=\\s*[\'"]([^\'"]+)[\'"]/', $serviceContent, $bm)) {
            return null;
        }

        $path = trim($bm[1], '/');

        return [
            'path' => $path,
            'method' => 'GET',
            'params' => ['page' => 1, 'limit' => 15],
            'data_key' => 'data',
            'kind' => 'list',
        ];
    }

    /**
     * API de opciones de filtros (p. ej. api/cursos/filters/options).
     *
     * @return array{path: string, method: string, params: array<string, mixed>, data_key: string, kind: string}|null
     */
    private function inferFilterLiveApi(string $root, string $content): ?array
    {
        $sources = [$content];
        if (preg_match_all("/from\\s+['\"]~\\/(composables\\/[^'\"]+)['\"]/", $content, $cm)) {
            foreach ($cm[1] as $rel) {
                $file = $this->resolveFrontFile($root, $rel);
                if ($file) {
                    $sources[] = $file['content'];
                }
            }
        }

        $serviceClass = null;
        $methodHint = null;
        foreach ($sources as $src) {
            if (preg_match('/([A-Za-z0-9_]+Service)\\.(getFiltros|filterOptions|getFilters|getFilterOptions)/', $src, $sm)) {
                $serviceClass = $sm[1];
                $methodHint = $sm[2];
                break;
            }
            if (preg_match('/filters\\/options/', $src)) {
                // path literal in service
            }
        }

        if ($serviceClass) {
            foreach ($sources as $src) {
                if (preg_match(
                    '/import\\s*\\{?[^}]*\\b' . preg_quote($serviceClass, '/') . '\\b[^}]*\\}?\\s*from\\s*[\'"]([^\'"]+)[\'"]/',
                    $src,
                    $im
                )) {
                    $importPath = preg_replace('#^~/#', '', $im[1]) ?? $im[1];
                    $file = $this->resolveFrontFile($root, $importPath);
                    if (!$file) {
                        continue;
                    }
                    $svc = $file['content'];
                    $base = null;
                    if (preg_match('/baseUrl\\s*=\\s*[\'"]([^\'"]+)[\'"]/', $svc, $bm)) {
                        $base = trim($bm[1], '/');
                    }
                    // `${this.baseUrl}/filters/options`
                    if (preg_match('/baseUrl\\}[\'\"]?\\s*\\/\\s*[\'\"]filters\\/options/', $svc)
                        || preg_match('/\\$\\{this\\.baseUrl\\}\\/filters\\/options/', $svc)
                        || preg_match('/[\'"][^\'"]*filters\\/options[\'"]/', $svc)) {
                        $path = ($base ?: 'api') . '/filters/options';
                        if ($base && !str_ends_with($base, '/filters/options')) {
                            $path = $base . '/filters/options';
                        }

                        return [
                            'path' => $path,
                            'method' => 'GET',
                            'params' => [],
                            'data_key' => 'data',
                            'kind' => 'filter_options',
                        ];
                    }
                    if ($base && $methodHint) {
                        // fallback común
                        return [
                            'path' => $base . '/filters/options',
                            'method' => 'GET',
                            'params' => [],
                            'data_key' => 'data',
                            'kind' => 'filter_options',
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, array{title: string, fields: array<int, array<string, mixed>>, actions: array<int, string>, live_api: ?array}>
     */
    private function extractModals(string $content): array
    {
        $out = [];
        if (!preg_match_all('/<UModal\\b[\\s\\S]*?<\\/UModal>/i', $content, $matches)) {
            return [];
        }

        foreach ($matches[0] as $block) {
            $title = 'Modal';
            if (preg_match('/<h[1-4][^>]*>\\s*([^<]{1,80})\\s*<\\/h[1-4]>/i', $block, $tm)) {
                $title = trim(html_entity_decode(strip_tags($tm[1])));
            } elseif (preg_match('/\\{\\{\\s*[^}]*\\?\\s*[\'"]([^\'"]+)[\'"]\\s*:\\s*[\'"]([^\'"]+)[\'"]/', $block, $tm2)) {
                // {{ isEditing ? 'Editar plan' : 'Nuevo plan' }}
                $title = $tm2[2] ?: $tm2[1];
            }

            $fields = [];
            if (preg_match_all('/<UFormField\\b([^>]*)>/i', $block, $ffs, PREG_SET_ORDER)) {
                foreach ($ffs as $ff) {
                    $attrs = $ff[1];
                    $label = $this->attrString($attrs, 'label') ?: 'Campo';
                    $fields[] = [
                        'key' => Str::slug($label),
                        'label' => $label,
                        'type' => 'text',
                        'value' => '',
                        'options' => [],
                    ];
                }
            }

            $actions = [];
            if (preg_match_all('/<UButton\\b[^>]*label="([^"]+)"/i', $block, $btns)) {
                $actions = array_values(array_unique($btns[1]));
            }

            if (count($fields) === 0 && count($actions) === 0) {
                continue;
            }

            $out[] = [
                'title' => $title,
                'fields' => $fields,
                'actions' => $actions ?: ['Cancelar', 'Guardar'],
                'live_api' => null,
            ];
        }

        return $out;
    }

    private function humanizeFilterName(string $name): string
    {
        $name = preg_replace('/^filterConfig/i', '', $name) ?? $name;
        $name = trim($name);
        if ($name === '') {
            return 'General';
        }

        return $this->humanizeIdentifier($name);
    }

    private function pageLabelHint(string $relPath): ?string
    {
        $rel = str_replace('\\', '/', $relPath);
        $rel = preg_replace('#^pages/#', '', $rel) ?? $rel;
        $rel = preg_replace('/\\.vue$/', '', $rel) ?? $rel;
        $parts = array_values(array_filter(explode('/', $rel)));
        if (!$parts) {
            return null;
        }
        $last = end($parts);
        if ($last === 'index' && count($parts) > 1) {
            $last = $parts[count($parts) - 2];
        }

        return $this->humanizeIdentifier(str_replace('-', ' ', (string) $last));
    }

    /**
     * @return array{path: string, content: string}|null
     */
    private function resolveFrontFile(string $root, string $relImport): ?array
    {
        $rel = str_replace('\\', '/', $relImport);
        $rel = preg_replace('/\\.ts$/', '', $rel) ?? $rel;
        $candidates = [
            $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel),
            $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel) . '.ts',
            $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel) . '.js',
            $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel) . DIRECTORY_SEPARATOR . 'index.ts',
        ];
        foreach ($candidates as $cand) {
            if (!is_file($cand)) {
                continue;
            }
            $content = @file_get_contents($cand);
            if ($content === false) {
                return null;
            }

            return [
                'path' => $this->relativePath($root, $cand),
                'content' => $content,
            ];
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function guessFiltersNearTable(string $content, string $attrs): array
    {
        // :filter-config="filterConfig" / filterConfigProspectos
        if (!preg_match('/:filter-config\\s*=\\s*"([^"]+)"/', $attrs, $m)
            && !preg_match("/:filter-config\\s*=\\s*'([^']+)'/", $attrs, $m)) {
            // también filterConfig sin binding name claro
            $all = $this->extractFilterConfigs($content);
            return $all[0]['fields'] ?? [];
        }

        $ref = trim($m[1]);
        $ref = preg_replace('/\\.value$/', '', $ref) ?? $ref;
        foreach ($this->extractFilterConfigs($content) as $fc) {
            if (strcasecmp($fc['name'], $ref) === 0 || str_contains(strtolower($fc['name']), strtolower($ref))) {
                return $fc['fields'];
            }
        }

        return [];
    }

    /**
     * @return array<int, array{name: string, fields: array<int, array<string, mixed>>}>
     */
    private function extractFilterConfigs(string $content): array
    {
        $out = [];
        // const filterConfigXxx = ref([ ... ]) o = ([ ... ]) / computed
        if (!preg_match_all(
            '/(?:const|let)\s+(filterConfig[A-Za-z0-9_]*)\s*=\s*(?:ref\s*(?:<[^>]*>)?\s*\(|computed\s*\(\s*\(\)\s*=>\s*)?\[/s',
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            return [];
        }

        foreach ($matches[1] as $idx => $nameMatch) {
            $name = $nameMatch[0];
            $start = $matches[0][$idx][1];
            $slice = substr($content, $start, 12000);
            $fields = $this->parseFilterFields($slice);
            if (count($fields) === 0) {
                continue;
            }
            $out[] = ['name' => $name, 'fields' => $fields];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseFilterFields(string $slice): array
    {
        $fields = [];
        // Objetos { label: '...', key: '...', type: '...' }
        if (!preg_match_all('/\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}/s', $slice, $objs)) {
            return [];
        }

        foreach ($objs[1] as $body) {
            if (!preg_match('/\\blabel\\s*:/', $body) || !preg_match('/\\bkey\\s*:/', $body)) {
                continue;
            }
            $label = $this->jsStringProp($body, 'label');
            $key = $this->jsStringProp($body, 'key');
            $type = $this->jsStringProp($body, 'type') ?: 'select';
            if ($label === null || $key === null) {
                continue;
            }
            $options = $this->parseOptions($body);
            $fields[] = [
                'label' => $label,
                'key' => $key,
                'type' => $type,
                'value' => $options[0]['value'] ?? ($type === 'select' ? 'todos' : ''),
                'options' => $options,
            ];
            // limitar objetos de un mismo config
            if (count($fields) >= 12) {
                break;
            }
        }

        return $fields;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function parseOptions(string $body): array
    {
        $options = [];
        if (!preg_match('/options\\s*:\\s*\[(.*?)\]/s', $body, $m)) {
            return $options;
        }
        if (preg_match_all('/label\\s*:\\s*[\'"]([^\'"]+)[\'"].*?value\\s*:\\s*[\'"]([^\'"]+)[\'"]/s', $m[1], $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $p) {
                $options[] = ['label' => $p[1], 'value' => $p[2]];
            }
        }

        return $options;
    }

    private function jsStringProp(string $body, string $prop): ?string
    {
        if (preg_match('/\\b' . preg_quote($prop, '/') . '\\s*:\\s*[\'"]([^\'"]+)[\'"]/', $body, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Extrae el cuerpo de `const/let tabs = [ ... ]` con balanceo de corchetes
     * (evita cortar en `[]` vacíos de ternarios: `cond ? [] : [{ label: 'Pagos' }]`).
     */
    private function extractTabsArrayBody(string $content): ?string
    {
        if (!preg_match('/(?:const|let)\s+tabs\s*=\s*\[/', $content, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $start = $m[0][1] + strlen($m[0][0]);
        $depth = 1;
        $len = strlen($content);
        for ($i = $start; $i < $len && $i < $start + 4000; $i++) {
            $ch = $content[$i];
            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $start, $i - $start);
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function extractTabLabels(string $content): array
    {
        $items = $this->extractTabItems($content);

        return array_values(array_map(fn ($item) => $item['label'], $items));
    }

    /**
     * Tabs con label + value (mejor para snapshot UTabs).
     *
     * @return array<int, array{key: string, label: string, content: string}>
     */
    private function extractTabItems(string $content): array
    {
        $items = [];
        $body = $this->extractTabsArrayBody($content);

        if ($body !== null) {
            // Objetos { label, value } incluso dentro de spreads / ternarios
            if (preg_match_all(
                '/\{[^{}]*label\\s*:\\s*[\'"]([^\'"]+)[\'"][^{}]*value\\s*:\\s*[\'"]([^\'"]+)[\'"][^{}]*\}/s',
                $body,
                $matches,
                PREG_SET_ORDER
            )) {
                foreach ($matches as $m) {
                    $items[] = [
                        'key' => $m[2],
                        'label' => $m[1],
                        'content' => '',
                    ];
                }
            }

            if (!$items && preg_match_all('/label\\s*:\\s*[\'"]([^\'"]+)[\'"]/', $body, $m2)) {
                foreach ($m2[1] as $label) {
                    $items[] = [
                        'key' => Str::slug($label) ?: 'tab',
                        'label' => $label,
                        'content' => '',
                    ];
                }
            }
        }

        // Fallback: labels frecuentes cerca de UTabs (páginas legacy)
        if (count($items) < 2 && preg_match_all(
            '/label\\s*:\\s*[\'"](Alumnos|Pagos|Prospectos|Por Embarcar|Clientes|Documentaci[oó]n|General|Embarque|Embarcados)[\'"]/u',
            $content,
            $m3
        )) {
            foreach ($m3[1] as $label) {
                $key = Str::slug($label) ?: 'tab';
                $exists = false;
                foreach ($items as $existing) {
                    if ($existing['key'] === $key || $existing['label'] === $label) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $items[] = [
                        'key' => $key,
                        'label' => $label,
                        'content' => '',
                    ];
                }
            }
        }

        // Dedup by key
        $seen = [];
        $out = [];
        foreach ($items as $item) {
            if (isset($seen[$item['key']])) {
                continue;
            }
            $seen[$item['key']] = true;
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $widgets
     * @return array<int, array<string, mixed>>
     */
    private function uniqueWidgets(array $widgets): array
    {
        $seen = [];
        $out = [];
        foreach ($widgets as $w) {
            $k = ($w['tipo'] ?? '') . ':' . ($w['key'] ?? '');
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $w;
        }

        return $out;
    }

    private function pageKeyFromPath(string $rel): string
    {
        $rel = str_replace('\\', '/', $rel);
        $rel = preg_replace('#^pages/#', '', $rel) ?? $rel;
        $rel = preg_replace('/\\.vue$/', '', $rel) ?? $rel;
        $rel = str_replace(['[', ']'], '', $rel);
        $parts = array_values(array_filter(explode('/', $rel)));
        // acortar index
        if (end($parts) === 'index') {
            array_pop($parts);
        }

        return Str::slug(implode('.', $parts), '.');
    }

    private function pageLabelFromPath(string $rel): string
    {
        $rel = str_replace('\\', '/', $rel);
        $rel = preg_replace('#^pages/#', '', $rel) ?? $rel;
        $rel = preg_replace('/\\.vue$/', '', $rel) ?? $rel;
        $rel = str_replace(['[', ']', '/index'], '', $rel);
        $parts = array_values(array_filter(explode('/', $rel)));
        $parts = array_map(fn ($p) => Str::of($p)->replace('-', ' ')->title()->toString(), $parts);

        return implode(' → ', $parts);
    }
}
