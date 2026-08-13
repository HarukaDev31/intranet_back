<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualMedia;
use App\Models\ManualUsuario\ManualPagina;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ManualUsuarioAdminService
{
    public function __construct(
        private ManualUsuarioCatalogService $catalog,
        private ManualUsuarioDbService $db,
        private ManualUsuarioTablaHydrator $tablaHydrator
    ) {
    }

    /**
     * @return array{
     *   roles: array<int, array<string, mixed>>,
     *   block_tipos: array<int, string>,
     *   ui_catalog: array<int, array<string, mixed>>,
     *   ui_catalog_categories: array<int, array{key: string, label: string}>
     * }
     */
    public function meta(): array
    {
        $roles = [];
        foreach ($this->catalog->roles() as $role) {
            $roles[] = [
                'slug' => $role['slug'],
                'id_grupo' => (int) ($role['id_grupo'] ?? 0),
                'nombre' => $role['nombre'] ?? $role['slug'],
            ];
        }

        $uiCatalog = $this->uiCatalogItems();

        return [
            'roles' => $roles,
            'block_tipos' => ManualBloque::tiposValidos(),
            'grupo_tipos' => ManualBloque::tiposGrupo(),
            'widget_tipos' => ManualBloque::tiposWidget(),
            'ui_catalog' => $uiCatalog,
            'ui_catalog_categories' => $this->uiCatalogCategories($uiCatalog),
            'page_widgets' => $this->pageWidgetsCatalog(),
        ];
    }

    /**
     * Páginas Vue → widgets (tablas/filtros/tabs) con snapshot listo para guardar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pageWidgetsCatalog(): array
    {
        $pages = config('manual_usuario_page_widgets', []);
        if (!is_array($pages)) {
            return [];
        }

        return array_values(array_map(function ($page) {
            $widgets = [];
            foreach (($page['widgets'] ?? []) as $w) {
                $widgets[] = [
                    'key' => (string) ($w['key'] ?? ''),
                    'label' => (string) ($w['label'] ?? ''),
                    'tipo' => ManualBloque::normalizeTipo((string) ($w['tipo'] ?? 'tabla')),
                    'component' => (string) ($w['component'] ?? ''),
                    'api_hint' => $w['api_hint'] ?? null,
                    'live_api' => is_array($w['live_api'] ?? null) ? $w['live_api'] : null,
                    'snapshot' => is_array($w['snapshot'] ?? null) ? $w['snapshot'] : [],
                ];
            }

            return [
                'key' => (string) ($page['key'] ?? ''),
                'label' => (string) ($page['label'] ?? ''),
                'page_path' => (string) ($page['page_path'] ?? ''),
                'widgets' => $widgets,
            ];
        }, $pages));
    }

    /**
     * Resuelve un widget de página a payload listo para guardar (cols reales + data live).
     *
     * @return array{tipo: string, titulo: string, payload: array<string, mixed>}
     */
    public function resolvePageWidget(
        string $pageKey,
        string $widgetKey,
        ?string $bearerToken = null,
        ?string $roleSlug = null
    ): array {
        foreach ($this->pageWidgetsCatalog() as $page) {
            if (($page['key'] ?? '') !== $pageKey) {
                continue;
            }
            foreach ($page['widgets'] as $w) {
                if (($w['key'] ?? '') !== $widgetKey) {
                    continue;
                }
                $tipo = ManualBloque::normalizeTipo((string) ($w['tipo'] ?? 'tabla'));
                $liveApi = is_array($w['live_api'] ?? null) ? $w['live_api'] : null;
                $snapshot = is_array($w['snapshot'] ?? null) ? $w['snapshot'] : [];
                if ($liveApi || in_array($tipo, [ManualBloque::TIPO_TABLA, ManualBloque::TIPO_FILTROS, ManualBloque::TIPO_MODAL], true)) {
                    $snapshot = $this->tablaHydrator->hydrate($snapshot, $liveApi, $bearerToken, $roleSlug, $tipo);
                }

                return [
                    'tipo' => $tipo,
                    'titulo' => (string) ($w['label'] ?? $widgetKey),
                    'payload' => [
                        'subtitulo' => $page['label'] ?? null,
                        'source' => [
                            'page_key' => $page['key'],
                            'page_path' => $page['page_path'] ?? null,
                            'widget_key' => $w['key'],
                            'component' => $w['component'] ?? null,
                            'api_hint' => $w['api_hint'] ?? null,
                            'live_api' => $liveApi,
                            'role_slug' => $roleSlug,
                        ],
                        'snapshot' => $snapshot,
                    ],
                ];
            }
        }

        throw new InvalidArgumentException('Widget de página no encontrado en el catálogo.');
    }

    /**
     * Re-hidrata snapshot de un bloque con live_api (tabla/filtros/modal/…).
         *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function hydrateLivePayload(array $payload, ?string $bearerToken, ?string $roleSlug = null, string $tipo = 'tabla'): array
    {
        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];
        $snapshot = is_array($payload['snapshot'] ?? null) ? $payload['snapshot'] : [];
        $liveApi = is_array($source['live_api'] ?? null)
            ? $source['live_api']
            : (is_array($snapshot['live_api'] ?? null) ? $snapshot['live_api'] : null);

        $payload['snapshot'] = $this->tablaHydrator->hydrate(
            $snapshot,
            $liveApi,
            $bearerToken,
            $roleSlug ?? ($source['role_slug'] ?? null),
            $tipo
        );
        if ($liveApi) {
            $payload['source'] = array_merge($source, [
                'live_api' => $liveApi,
                'role_slug' => $roleSlug ?? ($source['role_slug'] ?? null),
            ]);
        }

        return $payload;
    }

    /**
     * @deprecated usar hydrateLivePayload
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function hydrateTablaPayload(array $payload, ?string $bearerToken, ?string $roleSlug = null): array
    {
        return $this->hydrateLivePayload($payload, $bearerToken, $roleSlug, ManualBloque::TIPO_TABLA);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function uiCatalogItems(): array
    {
        $items = config('manual_usuario_ui_catalog', []);
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_map(function ($item) {
            return [
                'key' => (string) ($item['key'] ?? ''),
                'label' => (string) ($item['label'] ?? ''),
                'category' => (string) ($item['category'] ?? 'otros'),
                'module' => (string) ($item['module'] ?? ''),
                'source' => (string) ($item['source'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'html' => (string) ($item['html'] ?? ''),
                'css' => (string) ($item['css'] ?? ''),
            ];
        }, $items));
    }

    public function findUiCatalogItem(string $key): ?array
    {
        foreach ($this->uiCatalogItems() as $item) {
            if (($item['key'] ?? '') === $key) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{key: string, label: string}>
     */
    private function uiCatalogCategories(array $items): array
    {
        $labels = [
            'tabs' => 'Tabs',
            'modales' => 'Modales',
            'selects' => 'Selects',
            'toolbars' => 'Toolbars',
            'tablas' => 'Tablas',
            'filters' => 'Filtros',
            'otros' => 'Otros',
        ];
        $seen = [];
        foreach ($items as $item) {
            $key = (string) ($item['category'] ?? 'otros');
            $seen[$key] = $labels[$key] ?? ucfirst($key);
        }

        $out = [];
        foreach ($seen as $key => $label) {
            $out[] = ['key' => $key, 'label' => $label];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPages(?string $roleSlug = null, ?bool $publicado = null): array
    {
        $q = ManualPagina::query()->withCount('bloques')->orderBy('role_slug')->orderBy('orden')->orderBy('id');

        if ($roleSlug) {
            $q->where('role_slug', $roleSlug);
        }
        if ($publicado !== null) {
            $q->where('publicado', $publicado);
        }

        return $q->get()->map(function (ManualPagina $page) {
            return [
                'id' => $page->id,
                'role_slug' => $page->role_slug,
                'id_grupo' => $page->id_grupo,
                'modulo_key' => $page->modulo_key,
                'titulo' => $page->titulo,
                'descripcion' => $page->descripcion,
                'orden' => $page->orden,
                'publicado' => (bool) $page->publicado,
                'bloques_count' => (int) $page->bloques_count,
                'updated_at' => optional($page->updated_at)?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPage(int $id): array
    {
        $page = ManualPagina::query()
            ->with([
                'bloques' => function ($q) {
                    $q->whereNull('parent_id')->orderBy('orden')->orderBy('id');
                },
                'bloques.children.children.children',
            ])
            ->findOrFail($id);

        return $this->db->mapPageAdmin($page);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPage(array $data): array
    {
        $role = $this->catalog->findRoleBySlug((string) $data['role_slug']);
        if (!$role) {
            throw new InvalidArgumentException('Rol no encontrado en el catálogo del manual.');
        }

        $page = ManualPagina::query()->create([
            'role_slug' => $data['role_slug'],
            'id_grupo' => $data['id_grupo'] ?? ($role['id_grupo'] ?? null),
            'modulo_key' => $data['modulo_key'],
            'titulo' => $data['titulo'],
            'descripcion' => $data['descripcion'] ?? null,
            'orden' => $data['orden'] ?? $this->nextPageOrden((string) $data['role_slug']),
            'publicado' => array_key_exists('publicado', $data) ? (bool) $data['publicado'] : true,
        ]);

        return $this->getPage($page->id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updatePage(int $id, array $data): array
    {
        $page = ManualPagina::query()->findOrFail($id);

        if (isset($data['role_slug']) && $data['role_slug'] !== $page->role_slug) {
            $role = $this->catalog->findRoleBySlug((string) $data['role_slug']);
            if (!$role) {
                throw new InvalidArgumentException('Rol no encontrado en el catálogo del manual.');
            }
            $page->role_slug = $data['role_slug'];
            if (!array_key_exists('id_grupo', $data)) {
                $page->id_grupo = $role['id_grupo'] ?? null;
            }
        }

        foreach (['modulo_key', 'titulo', 'descripcion', 'orden', 'id_grupo'] as $field) {
            if (array_key_exists($field, $data)) {
                $page->{$field} = $data[$field];
            }
        }
        if (array_key_exists('publicado', $data)) {
            $page->publicado = (bool) $data['publicado'];
        }

        $page->save();

        return $this->getPage($page->id);
    }

    public function deletePage(int $id): void
    {
        ManualPagina::query()->findOrFail($id)->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createBlock(int $paginaId, array $data): array
    {
        $page = ManualPagina::query()->findOrFail($paginaId);
        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;

        if ($parentId) {
            $parent = ManualBloque::query()->findOrFail($parentId);
            if ((int) $parent->pagina_id !== (int) $page->id) {
                throw new InvalidArgumentException('El padre no pertenece a esta página.');
            }
            if (!ManualBloque::isContainer((string) $parent->tipo)) {
                throw new InvalidArgumentException('Solo un grupo o una línea de tiempo puede tener subbloques.');
            }
            $tipo = ManualBloque::normalizeTipo((string) ($data['tipo'] ?? ManualBloque::TIPO_TEXTO));
            if (!ManualBloque::isValidTipo($tipo)) {
                throw new InvalidArgumentException('Tipo de bloque no válido.');
            }
            // Dentro de timeline: solo widgets hoja (no anidar otro timeline/grupo).
            if (ManualBloque::isTimeline((string) $parent->tipo) && ManualBloque::isContainer($tipo)) {
                throw new InvalidArgumentException('En una línea de tiempo solo se agregan widgets (pasos), no contenedores.');
            }
        } else {
            // Raíz: siempre grupo (título + clave).
            $tipo = ManualBloque::TIPO_GRUPO;
        }

        if ($tipo === ManualBloque::TIPO_GRUPO) {
            $titulo = trim((string) ($data['titulo'] ?? ''));
            $clave = trim((string) ($data['clave'] ?? ''));
            if ($titulo === '' || $clave === '') {
                throw new InvalidArgumentException('Un grupo requiere título y clave (ruta).');
            }
        } elseif ($tipo === ManualBloque::TIPO_TIMELINE) {
            $titulo = trim((string) ($data['titulo'] ?? '')) ?: 'Línea de tiempo';
            $clave = null;
        } else {
            $titulo = $data['titulo'] ?? null;
            $clave = null;
        }

        $block = ManualBloque::query()->create([
            'pagina_id' => $page->id,
            'parent_id' => $parentId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'clave' => $clave,
            'payload' => $data['payload'] ?? $this->defaultPayload($tipo),
            'orden' => $data['orden'] ?? $this->nextBlockOrden($page->id, $parentId),
        ]);

        $block->load(['children.children.children']);

        return $this->db->mapBlockAdmin($block);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateBlock(int $id, array $data): array
    {
        $block = ManualBloque::query()->findOrFail($id);
        $isRoot = $block->parent_id === null;

        if (isset($data['tipo'])) {
            $tipo = ManualBloque::normalizeTipo((string) $data['tipo']);
            if (!ManualBloque::isValidTipo($tipo)) {
                throw new InvalidArgumentException('Tipo de bloque no válido.');
            }
            if ($isRoot && $tipo !== ManualBloque::TIPO_GRUPO) {
                throw new InvalidArgumentException('El bloque raíz debe ser grupo.');
            }
            $block->tipo = $tipo;
        }

        if (array_key_exists('titulo', $data)) {
            $block->titulo = $data['titulo'];
        }
        if (array_key_exists('clave', $data)) {
            $block->clave = $data['clave'];
        }
        if (array_key_exists('payload', $data)) {
            $payload = $data['payload'] ?? [];
            $tipoActual = ManualBloque::normalizeTipo((string) ($data['tipo'] ?? $block->tipo));
            if (in_array($tipoActual, [ManualBloque::TIPO_TABLA, ManualBloque::TIPO_FILTROS, ManualBloque::TIPO_MODAL], true)) {
                $page = ManualPagina::query()->find($block->pagina_id);
                $payload = $this->hydrateLivePayload(
                    is_array($payload) ? $payload : [],
                    $data['bearer_token'] ?? null,
                    $page?->role_slug,
                    $tipoActual
                );
            }
            $block->payload = $payload;
        }
        if (array_key_exists('orden', $data)) {
            $block->orden = (int) $data['orden'];
        }

        if (ManualBloque::isGrupo((string) $block->tipo)) {
            if (trim((string) $block->titulo) === '' || trim((string) $block->clave) === '') {
                throw new InvalidArgumentException('Un grupo requiere título y clave (ruta).');
            }
        }

        $block->save();
        $block->load(['children.children.children']);

        return $this->db->mapBlockAdmin($block);
    }

    public function deleteBlock(int $id): void
    {
        $block = ManualBloque::query()->with('children')->findOrFail($id);
        $this->deleteBlockTree($block);
    }

    private function deleteBlockTree(ManualBloque $block): void
    {
        $block->loadMissing('children');
        foreach ($block->children as $child) {
            $this->deleteBlockTree($child);
        }
        $block->delete();
    }

    /**
     * Reordenar bloques: [{id, orden}, ...]
     *
     * @param  array<int, array{id:int, orden:int}>  $items
     */
    public function reorderBlocks(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                ManualBloque::query()
                    ->where('id', (int) $item['id'])
                    ->update(['orden' => (int) $item['orden']]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadMedia(UploadedFile $file, ?string $alt = null, ?string $roleSlug = null, ?int $uploadedBy = null): array
    {
        $dir = trim((string) config('manual_usuario.storage_dir', 'manual'), '/');
        if ($roleSlug) {
            $dir .= '/' . Str::slug($roleSlug);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $name = now()->format('YmdHis') . '_' . Str::random(8) . '.' . $ext;
        $path = $file->storeAs($dir, $name, config('manual_usuario.storage_disk', 'local'));

        $media = ManualMedia::query()->create([
            'path' => $path,
            'alt' => $alt,
            'mime' => $file->getMimeType(),
            'uploaded_by' => $uploadedBy,
        ]);

        return $this->mapMedia($media);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMedia(?string $roleSlug = null): array
    {
        $q = ManualMedia::query()->orderByDesc('id');
        if ($roleSlug) {
            $prefix = trim((string) config('manual_usuario.storage_dir', 'manual'), '/') . '/' . Str::slug($roleSlug) . '/';
            $q->where('path', 'like', $prefix . '%');
        }

        return $q->limit(200)->get()->map(fn (ManualMedia $m) => $this->mapMedia($m))->values()->all();
    }

    public function deleteMedia(int $id): void
    {
        $media = ManualMedia::query()->findOrFail($id);
        $disk = config('manual_usuario.storage_disk', 'local');
        if ($media->path && Storage::disk($disk)->exists($media->path)) {
            Storage::disk($disk)->delete($media->path);
        }
        $media->delete();
    }

    public function absoluteMediaPath(int $id): ?string
    {
        $media = ManualMedia::query()->find($id);
        if (!$media || !$media->path) {
            return null;
        }

        $disk = config('manual_usuario.storage_disk', 'local');
        if (!Storage::disk($disk)->exists($media->path)) {
            return null;
        }

        return Storage::disk($disk)->path($media->path);
    }

    public function findMedia(int $id): ?ManualMedia
    {
        return ManualMedia::query()->find($id);
    }

    private function nextPageOrden(string $roleSlug): int
    {
        return ((int) ManualPagina::query()->where('role_slug', $roleSlug)->max('orden')) + 1;
    }

    private function nextBlockOrden(int $paginaId, ?int $parentId = null): int
    {
        $q = ManualBloque::query()->where('pagina_id', $paginaId);
        if ($parentId === null) {
            $q->whereNull('parent_id');
        } else {
            $q->where('parent_id', $parentId);
        }

        return ((int) $q->max('orden')) + 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPayload(string $tipo): array
    {
        $tipo = ManualBloque::normalizeTipo($tipo);

        return match ($tipo) {
            ManualBloque::TIPO_GRUPO => [
                'subtitulo' => null,
                'snapshot' => [],
            ],
            ManualBloque::TIPO_TEXTO => [
                'subtitulo' => null,
                'snapshot' => ['body' => ''],
            ],
            ManualBloque::TIPO_CALLOUT => [
                'subtitulo' => null,
                'snapshot' => ['tone' => 'info', 'title' => '', 'body' => ''],
            ],
            ManualBloque::TIPO_MEDIA => [
                'subtitulo' => null,
                'snapshot' => ['media_id' => null, 'alt' => '', 'caption' => '', 'url' => null],
            ],
            ManualBloque::TIPO_FLOW => [
                'subtitulo' => null,
                'snapshot' => [
                    'hint' => 'Pasos del flujo',
                    'steps' => [
                        ['title' => 'Paso 1', 'body' => ''],
                        ['title' => 'Paso 2', 'body' => ''],
                    ],
                ],
            ],
            ManualBloque::TIPO_EMBED => [
                'subtitulo' => null,
                'snapshot' => [
                    'catalog_key' => null,
                    'label' => '',
                    'html' => '',
                    'css' => '',
                ],
            ],
            ManualBloque::TIPO_TABLA => [
                'subtitulo' => null,
                'source' => null,
                'snapshot' => [
                    'columns' => ['Columna'],
                    'filters' => [],
                    'rows' => [['Valor']],
                ],
            ],
            ManualBloque::TIPO_FILTROS => [
                'subtitulo' => null,
                'source' => null,
                'snapshot' => [
                    'fields' => [
                        ['type' => 'select', 'label' => 'Filtro', 'value' => '', 'options' => []],
                    ],
                ],
            ],
            ManualBloque::TIPO_TABS => [
                'subtitulo' => null,
                'source' => null,
                'snapshot' => [
                    'active' => 'tab1',
                    'tabs' => [['key' => 'tab1', 'label' => 'Tab 1', 'content' => '']],
                ],
            ],
            ManualBloque::TIPO_TOOLBAR => [
                'subtitulo' => null,
                'source' => null,
                'snapshot' => [
                    'buttons' => [['label' => 'Acción', 'color' => 'primary', 'variant' => 'solid']],
                ],
            ],
            ManualBloque::TIPO_MODAL => [
                'subtitulo' => null,
                'source' => null,
                'snapshot' => [
                    'title' => 'Modal',
                    'fields' => [
                        ['key' => 'campo', 'label' => 'Campo', 'type' => 'text', 'value' => '', 'options' => []],
                    ],
                    'actions' => ['Cancelar', 'Guardar'],
                ],
            ],
            ManualBloque::TIPO_CARD => [
                'subtitulo' => null,
                'source' => null,
                'snapshot' => [
                    'title' => 'Card',
                    'icon' => null,
                    'body' => '',
                    'fields' => [],
                    'buttons' => [],
                ],
            ],
            ManualBloque::TIPO_ACCION => [
                'subtitulo' => null,
                'source' => null,
                'snapshot' => [
                    'label' => 'Acción',
                    'icon' => null,
                    'color' => 'primary',
                    'variant' => 'solid',
                ],
            ],
            ManualBloque::TIPO_TIMELINE => [
                'subtitulo' => null,
                'snapshot' => [
                    'orientation' => 'horizontal',
                ],
            ],
            default => ['subtitulo' => null, 'snapshot' => []],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMedia(ManualMedia $media): array
    {
        return [
            'id' => $media->id,
            'path' => $media->path,
            'alt' => $media->alt,
            'mime' => $media->mime,
            'uploaded_by' => $media->uploaded_by,
            'url' => url('/api/manual-usuario/media/' . $media->id),
            'created_at' => optional($media->created_at)?->toIso8601String(),
        ];
    }
}
