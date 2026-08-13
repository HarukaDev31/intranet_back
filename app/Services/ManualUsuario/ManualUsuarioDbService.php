<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualMedia;
use App\Models\ManualUsuario\ManualPagina;
use App\Traits\UsesObjectStorage;
use Illuminate\Support\Str;

class ManualUsuarioDbService
{
    use UsesObjectStorage;
    public function hasPublishedPages(string $roleSlug): bool
    {
        return ManualPagina::query()
            ->where('role_slug', $roleSlug)
            ->where('publicado', true)
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pagesForRole(string $roleSlug): array
    {
        $pages = ManualPagina::query()
            ->with(['bloques' => function ($q) {
                $q->whereNull('parent_id')->orderBy('orden')->orderBy('id');
            }, 'bloques.children' => function ($q) {
                $q->orderBy('orden')->orderBy('id');
            }, 'bloques.children.children' => function ($q) {
                $q->orderBy('orden')->orderBy('id');
            }, 'bloques.children.children.children' => function ($q) {
                $q->orderBy('orden')->orderBy('id');
            }])
            ->where('role_slug', $roleSlug)
            ->where('publicado', true)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        return $pages->map(function (ManualPagina $page) {
            return $this->mapPage($page);
        })->values()->all();
    }

    /**
     * Páginas publicadas de todos los roles (para PDF global).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function pagesGroupedByRole(): array
    {
        $pages = ManualPagina::query()
            ->with(['bloques' => function ($q) {
                $q->whereNull('parent_id')->orderBy('orden')->orderBy('id');
            }, 'bloques.children.children.children'])
            ->where('publicado', true)
            ->orderBy('role_slug')
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $grouped = [];
        foreach ($pages as $page) {
            $grouped[$page->role_slug][] = $this->mapPage($page);
        }

        return $grouped;
    }

    /**
     * Ruta absoluta de un media CMS (para PDF / assets).
     */
    public function absoluteMediaPathForPdf(int $id): ?string
    {
        $media = ManualMedia::query()->find($id);
        if (!$media || !$media->path) {
            return null;
        }

        $uploadPath = $this->storageUploadPathFromDb((string) $media->path) ?: (string) $media->path;
        try {
            return $this->objectStorage()->localPath($uploadPath);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * URL pública CDN/S3 para media del manual (o fallback API).
     */
    public function resolveMediaPublicUrl(string $path, ?int $mediaId = null): ?string
    {
        $uploadPath = $this->storageUploadPathFromDb($path) ?: ltrim(str_replace('\\', '/', $path), '/');
        try {
            $url = $this->objectStorage()->url($uploadPath);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        } catch (\Throwable $e) {
            // fallback
        }

        return $mediaId ? url('/api/manual-usuario/media/' . $mediaId) : null;
    }

    /**
     * HTML de una página CMS para DomPDF (misma jerarquía que el lector).
     */
    public function pageToHtml(array $page): string
    {
        $titulo = e((string) ($page['titulo'] ?? ''));
        $desc = trim((string) ($page['descripcion'] ?? ''));
        $parts = [];
        $parts[] = '<div class="page-card">';
        $parts[] = '<div class="page-card-header">';
        $parts[] = '<div class="page-card-title">' . $titulo . '</div>';
        if ($desc !== '') {
            $parts[] = '<div class="page-card-desc">' . e($desc) . '</div>';
        }
        $parts[] = '</div>';
        $parts[] = '<div class="page-card-body">';
        foreach ($page['blocks'] ?? [] as $block) {
            if (is_array($block)) {
                $parts[] = $this->blockTreeToHtml($block, 0);
            }
        }
        $parts[] = '</div></div>';

        return implode("\n", $parts);
    }

    /**
     * Índice TOC: páginas → grupos (como el menú del lector).
     *
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<int, array{title: string, children: array<int, string>}>
     */
    public function pagesToToc(array $pages): array
    {
        $toc = [];
        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }
            $children = [];
            foreach ($page['blocks'] ?? [] as $block) {
                if (!is_array($block)) {
                    continue;
                }
                if (ManualBloque::normalizeTipo((string) ($block['tipo'] ?? '')) !== ManualBloque::TIPO_GRUPO) {
                    continue;
                }
                $t = trim((string) ($block['titulo'] ?? $block['clave'] ?? ''));
                if ($t !== '') {
                    $children[] = $t;
                }
            }
            $toc[] = [
                'title' => (string) ($page['titulo'] ?? 'Página'),
                'children' => $children,
            ];
        }

        return $toc;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function blockTreeToHtml(array $block, int $depth = 0): string
    {
        $tipo = ManualBloque::normalizeTipo((string) ($block['tipo'] ?? ''));

        if ($tipo === ManualBloque::TIPO_GRUPO) {
            return $this->grupoToHtml($block, $depth);
        }
        if ($tipo === ManualBloque::TIPO_TIMELINE) {
            return $this->timelineToHtml($block);
        }

        return '<div class="widget">' . $this->blockToHtml($block) . '</div>';
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function grupoToHtml(array $block, int $depth): string
    {
        $titulo = trim((string) ($block['titulo'] ?? ''));
        $clave = trim((string) ($block['clave'] ?? ''));
        $payload = is_array($block['payload'] ?? null) ? $block['payload'] : [];
        $subtitulo = trim((string) ($payload['subtitulo'] ?? ''));
        $levelClass = $depth === 0 ? 'grupo' : 'grupo grupo-nested';

        $html = '<div class="' . $levelClass . '">';
        if ($titulo !== '') {
            $html .= $this->linkedTitleHtml($titulo, $clave, 'grupo-title');
        }
        if ($clave !== '' && !$this->isRouteLike($clave)) {
            $html .= '<div class="grupo-clave">' . e($clave) . '</div>';
        }
        if ($subtitulo !== '' && !$this->isRouteLike($subtitulo)) {
            $html .= '<div class="grupo-sub">' . e($subtitulo) . '</div>';
        }
        $children = is_array($block['children'] ?? null) ? $block['children'] : [];
        if ($children !== []) {
            $html .= '<div class="grupo-children">';
            foreach ($children as $child) {
                if (is_array($child)) {
                    $html .= $this->blockTreeToHtml($child, $depth + 1);
                }
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function timelineToHtml(array $block): string
    {
        $titulo = trim((string) ($block['titulo'] ?? ''));
        $payload = is_array($block['payload'] ?? null) ? $block['payload'] : [];
        $subtitulo = trim((string) ($payload['subtitulo'] ?? ''));
        $html = '<div class="timeline">';
        if ($titulo !== '') {
            $html .= $this->linkedTitleHtml($titulo, $subtitulo, 'widget-title');
        }
        if ($subtitulo !== '' && !$this->isRouteLike($subtitulo)) {
            $html .= '<div class="muted">' . e($subtitulo) . '</div>';
        }
        $html .= '<table class="timeline-table" width="100%" cellpadding="0" cellspacing="0"><tr>';
        $children = is_array($block['children'] ?? null) ? $block['children'] : [];
        $n = count($children);
        foreach ($children as $i => $child) {
            if (!is_array($child)) {
                continue;
            }
            $stepTitle = trim((string) ($child['titulo'] ?? $child['tipo'] ?? 'Paso'));
            $html .= '<td class="timeline-step" width="' . max(1, (int) floor(100 / max(1, $n))) . '%">';
            $html .= '<div class="timeline-num">' . ($i + 1) . '</div>';
            $html .= '<div class="timeline-step-title">' . e($stepTitle) . '</div>';
            $html .= '<div class="timeline-step-body">' . $this->blockToHtml(array_merge($child, ['titulo' => ''])) . '</div>';
            $html .= '</td>';
            if ($i < $n - 1) {
                $html .= '<td class="timeline-arrow" width="24">→</td>';
            }
        }
        $html .= '</tr></table></div>';

        return $html;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapPageAdmin(ManualPagina $page): array
    {
        $base = $this->mapPage($page);
        $base['role_slug'] = $page->role_slug;
        $base['id_grupo'] = $page->id_grupo;
        $base['publicado'] = (bool) $page->publicado;
        $base['created_at'] = optional($page->created_at)?->toIso8601String();
        $base['updated_at'] = optional($page->updated_at)?->toIso8601String();

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapBlockAdmin(ManualBloque $bloque): array
    {
        return $this->mapBlock($bloque);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPage(ManualPagina $page): array
    {
        $roots = $page->relationLoaded('bloques')
            ? $page->bloques
            : $page->bloques()->whereNull('parent_id')->with(['children.children.children'])->orderBy('orden')->orderBy('id')->get();

        return [
            'id' => $page->id,
            'modulo_key' => $page->modulo_key,
            'titulo' => $page->titulo,
            'descripcion' => $page->descripcion,
            'orden' => $page->orden,
            'blocks' => $roots->map(fn (ManualBloque $bloque) => $this->mapBlock($bloque))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBlock(ManualBloque $bloque): array
    {
        $tipo = ManualBloque::normalizeTipo((string) $bloque->tipo);
        $payload = $this->normalizePayload($tipo, is_array($bloque->payload) ? $bloque->payload : []);

        $snap = $payload['snapshot'] ?? [];
        if ($tipo === ManualBloque::TIPO_MEDIA && !empty($snap['media_id'])) {
            $mediaId = (int) $snap['media_id'];
            $media = ManualMedia::query()->find($mediaId);
            if ($media && $media->path) {
                $publicUrl = $this->resolveMediaPublicUrl((string) $media->path, $mediaId);
                if ($publicUrl) {
                    $snap['url'] = $publicUrl;
                }
            } elseif (empty($snap['url'])) {
                $snap['url'] = url('/api/manual-usuario/media/' . $mediaId);
            }
            $payload['snapshot'] = $snap;
        }

        $children = [];
        if ($bloque->relationLoaded('children')) {
            $children = $bloque->children->map(fn (ManualBloque $child) => $this->mapBlock($child))->values()->all();
        }

        return [
            'id' => $bloque->id,
            'pagina_id' => $bloque->pagina_id,
            'parent_id' => $bloque->parent_id,
            'tipo' => $tipo,
            'titulo' => $bloque->titulo,
            'clave' => $bloque->clave,
            'payload' => $payload,
            'orden' => $bloque->orden,
            'children' => $children,
        ];
    }

    /**
     * Unifica payload legacy → { subtitulo, source?, snapshot }.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(string $tipo, array $payload): array
    {
        if (isset($payload['snapshot']) && is_array($payload['snapshot'])) {
            return [
                'subtitulo' => $payload['subtitulo'] ?? null,
                'source' => $payload['source'] ?? null,
                'snapshot' => $payload['snapshot'],
            ];
        }

        // Legacy flat payloads
        $snapshot = $payload;
        unset($snapshot['subtitulo'], $snapshot['source']);

        if ($tipo === ManualBloque::TIPO_TEXTO && isset($payload['body'])) {
            $snapshot = ['body' => $payload['body']];
        }
        if ($tipo === ManualBloque::TIPO_CALLOUT) {
            $snapshot = [
                'tone' => $payload['tone'] ?? 'info',
                'title' => $payload['title'] ?? '',
                'body' => $payload['body'] ?? '',
            ];
        }
        if ($tipo === ManualBloque::TIPO_MEDIA) {
            $snapshot = [
                'media_id' => $payload['media_id'] ?? null,
                'alt' => $payload['alt'] ?? '',
                'caption' => $payload['caption'] ?? '',
                'url' => $payload['url'] ?? null,
            ];
        }
        if ($tipo === ManualBloque::TIPO_FLOW) {
            $snapshot = [
                'hint' => $payload['hint'] ?? null,
                'steps' => $payload['steps'] ?? [],
            ];
        }
        if ($tipo === ManualBloque::TIPO_EMBED) {
            $snapshot = [
                'catalog_key' => $payload['catalog_key'] ?? null,
                'label' => $payload['label'] ?? '',
                'html' => $payload['html'] ?? '',
                'css' => $payload['css'] ?? '',
            ];
        }
        if ($tipo === ManualBloque::TIPO_TABLA) {
            $snapshot = [
                'columns' => $payload['columns'] ?? $payload['headers'] ?? [],
                'filters' => $payload['filters'] ?? [],
                'rows' => $payload['rows'] ?? [],
            ];
        }
        if ($tipo === ManualBloque::TIPO_GRUPO) {
            $snapshot = [];
        }
        if ($tipo === ManualBloque::TIPO_FILTROS) {
            $snapshot = [
                'fields' => $payload['fields'] ?? [],
                'hint' => $payload['hint'] ?? null,
            ];
        }
        if ($tipo === ManualBloque::TIPO_TABS) {
            $snapshot = [
                'active' => $payload['active'] ?? null,
                'tabs' => $payload['tabs'] ?? [],
                'hint' => $payload['hint'] ?? null,
            ];
        }
        if ($tipo === ManualBloque::TIPO_TOOLBAR) {
            $snapshot = [
                'buttons' => $payload['buttons'] ?? [],
                'hint' => $payload['hint'] ?? null,
            ];
        }

        return [
            'subtitulo' => $payload['subtitulo'] ?? null,
            'source' => $payload['source'] ?? null,
            'snapshot' => $snapshot,
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function blockToHtml(array $block): string
    {
        $titulo = trim((string) ($block['titulo'] ?? ''));
        $payload = is_array($block['payload'] ?? null) ? $block['payload'] : [];
        $tipo = ManualBloque::normalizeTipo((string) ($block['tipo'] ?? ''));
        $snap = is_array($payload['snapshot'] ?? null) ? $payload['snapshot'] : $payload;
        $out = [];

        if ($titulo !== '' && $tipo !== ManualBloque::TIPO_GRUPO) {
            $out[] = $this->linkedTitleHtml($titulo, (string) ($payload['subtitulo'] ?? ''), 'widget-title');
        }
        if (!empty($payload['subtitulo']) && !$this->isRouteLike((string) $payload['subtitulo'])) {
            $subClass = $tipo === ManualBloque::TIPO_MEDIA ? 'media-subtitle' : 'muted';
            $out[] = '<div class="' . $subClass . '">' . e((string) $payload['subtitulo']) . '</div>';
        }

        switch ($tipo) {
            case ManualBloque::TIPO_TEXTO:
                $out[] = '<div class="texto">' . nl2br(e((string) ($snap['body'] ?? ''))) . '</div>';
                break;

            case ManualBloque::TIPO_TOOLBAR:
                // DomPDF estira inline-block con fondo; usamos tabla.
                $out[] = '<table class="chip-row" cellpadding="0" cellspacing="0"><tr>';
                foreach ((array) ($snap['buttons'] ?? []) as $btn) {
                    if (!is_array($btn)) {
                        continue;
                    }
                    $label = (string) ($btn['label'] ?? '');
                    if ($label === '') {
                        continue;
                    }
                    $out[] = '<td class="btn">' . e($label) . '</td>';
                }
                $out[] = '</tr></table>';
                break;

            case ManualBloque::TIPO_ACCION:
                $label = (string) ($snap['label'] ?? '');
                if ($label === '') {
                    $label = $titulo !== '' ? $titulo : 'Acción';
                }
                $out[] = '<table class="chip-row" cellpadding="0" cellspacing="0"><tr>';
                $out[] = '<td class="btn btn-primary">' . e($label) . '</td>';
                $out[] = '</tr></table>';
                break;

            case ManualBloque::TIPO_FILTROS:
                $fields = array_values(array_filter(
                    (array) ($snap['fields'] ?? []),
                    static fn ($f) => is_array($f)
                ));
                if ($fields !== []) {
                    $out[] = '<table class="filters-table" width="100%" cellpadding="0" cellspacing="0"><tr>';
                    $cols = min(3, count($fields));
                    $i = 0;
                    foreach ($fields as $f) {
                        if ($i > 0 && $i % $cols === 0) {
                            $out[] = '</tr><tr>';
                        }
                        $label = (string) ($f['label'] ?? $f['key'] ?? 'Filtro');
                        $value = (string) ($f['value'] ?? '');
                        $opts = is_array($f['options'] ?? null) ? $f['options'] : [];
                        $optLabels = [];
                        foreach ($opts as $o) {
                            if (is_array($o)) {
                                $optLabels[] = (string) ($o['label'] ?? $o['value'] ?? '');
                            }
                        }
                        $shown = $value !== ''
                            ? $value
                            : (implode(' / ', array_slice(array_filter($optLabels), 0, 3)) ?: '—');
                        $out[] = '<td class="filter-field">';
                        $out[] = '<div class="filter-label">' . e($label) . '</div>';
                        $out[] = '<div class="filter-control">' . e($shown) . '</div>';
                        $out[] = '</td>';
                        $i++;
                    }
                    $out[] = '</tr></table>';
                }
                if (!empty($snap['hint'])) {
                    $out[] = '<div class="muted">' . e((string) $snap['hint']) . '</div>';
                }
                break;

            case ManualBloque::TIPO_TABS:
                // DomPDF: inline-block + background se estira a toda la página.
                $active = (string) ($snap['active'] ?? '');
                $out[] = '<table class="tabs-table" cellpadding="0" cellspacing="0"><tr>';
                foreach ((array) ($snap['tabs'] ?? []) as $tab) {
                    if (!is_array($tab)) {
                        continue;
                    }
                    $key = (string) ($tab['key'] ?? '');
                    $label = (string) ($tab['label'] ?? $key);
                    $cls = ($active !== '' && $key === $active) ? 'tab tab-active' : 'tab';
                    $out[] = '<td class="' . $cls . '">' . e($label) . '</td>';
                }
                $out[] = '</tr></table>';
                break;

            case ManualBloque::TIPO_TABLA:
                $out[] = $this->tablaToHtml($snap);
                break;

            case ManualBloque::TIPO_CALLOUT:
                $tone = (string) ($snap['tone'] ?? 'info');
                $callTitle = (string) ($snap['title'] ?? '');
                $text = (string) ($snap['body'] ?? '');
                $out[] = '<div class="callout callout-' . e($tone) . '">';
                if ($callTitle !== '') {
                    $out[] = '<div class="callout-title">' . e($callTitle) . '</div>';
                }
                $out[] = '<div>' . nl2br(e($text)) . '</div>';
                $out[] = '</div>';
                break;

            case ManualBloque::TIPO_MEDIA:
                $caption = (string) ($snap['caption'] ?? $snap['alt'] ?? 'Captura');
                $mediaId = (int) ($snap['media_id'] ?? 0);
                $embedded = false;
                if ($mediaId > 0) {
                    $absolute = $this->absoluteMediaPathForPdf($mediaId);
                    if ($absolute && is_readable($absolute)) {
                        $bin = @file_get_contents($absolute);
                        if ($bin !== false && $bin !== '') {
                            $mime = @mime_content_type($absolute) ?: 'image/png';
                            if (!str_starts_with((string) $mime, 'image/')) {
                                $mime = 'image/png';
                            }
                            $out[] = '<div class="media"><img src="data:' . $mime . ';base64,' . base64_encode($bin)
                                . '" alt="' . e($caption) . '" style="max-width:92%;max-height:280px;"></div>';
                            $embedded = true;
                        }
                    }
                }
                if (!$embedded) {
                    if ($mediaId > 0) {
                        $out[] = '<div class="media"><img src="' . e(url('/api/manual-usuario/media/' . $mediaId))
                            . '" alt="' . e($caption) . '"></div>';
                    } elseif (!empty($snap['url']) && !str_starts_with((string) $snap['url'], 'http')) {
                        $out[] = '<div class="media"><img src="' . e((string) $snap['url']) . '" alt="' . e($caption) . '"></div>';
                    } else {
                        $out[] = '<div class="media-placeholder">Captura pendiente</div>';
                    }
                }
                if ($caption !== '') {
                    $out[] = '<div class="media-caption">' . e($caption) . '</div>';
                }
                break;

            case ManualBloque::TIPO_EMBED:
                $label = (string) ($snap['label'] ?? 'Componente UI');
                $out[] = '<div class="embed-box"><strong>UI</strong> — ' . e($label) . '</div>';
                break;

            case ManualBloque::TIPO_CARD:
                $out[] = '<div class="card-box">';
                foreach ((array) ($snap['fields'] ?? []) as $f) {
                    if (!is_array($f)) {
                        continue;
                    }
                    $label = (string) ($f['label'] ?? $f['key'] ?? '');
                    $value = (string) ($f['value'] ?? '');
                    $out[] = '<div class="card-row"><span class="card-label">' . e($label) . '</span> '
                        . '<span class="card-value">' . e($value) . '</span></div>';
                }
                $out[] = '</div>';
                break;

            case ManualBloque::TIPO_MODAL:
                $modalTitle = (string) ($snap['title'] ?? '');
                if ($modalTitle === '') {
                    $modalTitle = $titulo !== '' ? $titulo : 'Modal';
                }
                $out[] = '<div class="modal-box">';
                $out[] = '<div class="modal-title">' . e($modalTitle) . '</div>';
                foreach ((array) ($snap['fields'] ?? []) as $f) {
                    if (!is_array($f)) {
                        continue;
                    }
                    $label = (string) ($f['label'] ?? $f['key'] ?? '');
                    $out[] = '<div class="filter-field"><div class="filter-label">' . e($label) . '</div>'
                        . '<div class="filter-control">' . e((string) ($f['value'] ?? '—')) . '</div></div>';
                }
                $actions = is_array($snap['actions'] ?? null) ? $snap['actions'] : [];
                if ($actions !== []) {
                    $out[] = '<table class="chip-row" cellpadding="0" cellspacing="0"><tr>';
                    foreach ($actions as $a) {
                        $out[] = '<td class="btn">' . e(is_string($a) ? $a : (string) ($a['label'] ?? '')) . '</td>';
                    }
                    $out[] = '</tr></table>';
                }
                $out[] = '</div>';
                break;

            case ManualBloque::TIPO_FLOW:
                $out[] = '<div class="flow">';
                if (!empty($snap['hint'])) {
                    $out[] = '<div class="muted">' . e((string) $snap['hint']) . '</div>';
                }
                $n = 1;
                foreach ((array) ($snap['steps'] ?? []) as $step) {
                    if (!is_array($step)) {
                        continue;
                    }
                    $out[] = '<table class="flow-table" cellpadding="0" cellspacing="0"><tr>';
                    $out[] = '<td class="flow-num">' . $n . '</td>';
                    $out[] = '<td class="flow-body"><div class="flow-step-title">' . e((string) ($step['title'] ?? '')) . '</div>'
                        . '<div>' . nl2br(e((string) ($step['body'] ?? ''))) . '</div></td>';
                    $out[] = '</tr></table>';
                    $n++;
                }
                $out[] = '</div>';
                break;

            case ManualBloque::TIPO_GRUPO:
                break;

            default:
                if (!empty($snap['body'])) {
                    $out[] = '<div class="texto">' . nl2br(e((string) $snap['body'])) . '</div>';
                }
                break;
        }

        return implode("\n", $out);
    }

    /**
     * @param  array<string, mixed>  $snap
     */
    private function tablaToHtml(array $snap): string
    {
        $cols = is_array($snap['columns'] ?? null) ? $snap['columns'] : [];
        $headers = [];
        foreach ($cols as $i => $col) {
            if (is_string($col)) {
                $headers[] = $col;
            } elseif (is_array($col)) {
                $headers[] = (string) ($col['header'] ?? $col['label'] ?? $col['accessorKey'] ?? ('Col ' . ($i + 1)));
            }
        }
        $rows = is_array($snap['rows'] ?? null) ? $snap['rows'] : [];
        if ($headers === [] && $rows === []) {
            return '<div class="muted">Tabla sin datos</div>';
        }

        $html = '<table class="data-table" width="100%" cellpadding="0" cellspacing="0">';
        if ($headers !== []) {
            $html .= '<thead><tr>';
            foreach ($headers as $h) {
                $html .= '<th>' . e($h) . '</th>';
            }
            $html .= '</tr></thead>';
        }
        $html .= '<tbody>';
        foreach (array_slice($rows, 0, 50) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $html .= '<tr>';
            if ($cols !== []) {
                foreach ($cols as $i => $col) {
                    $key = is_array($col) ? (string) ($col['accessorKey'] ?? $col['key'] ?? '') : '';
                    $type = is_array($col) ? (string) ($col['type'] ?? 'text') : 'text';
                    $val = $key !== '' && array_key_exists($key, $row) ? $row[$key] : ($row[$i] ?? '');
                    if ($type === 'buttons' && is_array($col['buttons'] ?? null)) {
                        $labels = collect($col['buttons'])->pluck('label')->filter()->implode(' · ');
                        $cell = $labels !== '' ? $labels : '⋯';
                    } elseif ($type === 'pago_grid') {
                        $details = is_array($row['pagos_details'] ?? null) ? $row['pagos_details'] : [];
                        $slots = max(1, (int) ($col['slots'] ?? 4));
                        $parts = [];
                        foreach (array_slice($details, 0, $slots) as $p) {
                            if (!is_array($p)) {
                                continue;
                            }
                            $parts[] = e($this->scalarForPdf($p['monto'] ?? ''));
                        }
                        $empty = $slots - count($parts);
                        for ($ei = 0; $ei < $empty; $ei++) {
                            $parts[] = '+';
                        }
                        $cell = implode(' · ', $parts) ?: '+';
                    } elseif ($type === 'multiline') {
                        $cell = str_replace(["\n", ' · '], '<br>', e($this->scalarForPdf($val)));
                    } else {
                        $cell = e($this->scalarForPdf($val));
                        if ($type === 'select' && $cell !== '') {
                            $cell = '<span class="pill">' . $cell . '</span>';
                        }
                    }
                    $html .= '<td>' . $cell . '</td>';
                }
            } else {
                foreach ($row as $val) {
                    if (is_string($val) || is_numeric($val) || is_bool($val) || $val === null) {
                        $html .= '<td>' . e($this->scalarForPdf($val)) . '</td>';
                    }
                }
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        if (count($rows) > 50) {
            $html .= '<div class="muted">… y ' . (count($rows) - 50) . ' filas más</div>';
        }

        return $html;
    }

    private function isRouteLike(string $raw): bool
    {
        $t = trim($raw);
        if ($t === '' || preg_match('/\s/', $t)) {
            return false;
        }
        if (preg_match('#^https?://#i', $t)) {
            return true;
        }
        if (str_starts_with($t, '/') && strlen($t) > 1) {
            return true;
        }

        return (bool) (preg_match('/^[a-z0-9][\w\-\\.\\/?=&%#]*$/i', $t)
            && (str_contains($t, '/') || str_contains($t, '?')));
    }

    /**
     * @return array{href: string, external: bool}|null
     */
    private function parseManualRoute(string $raw): ?array
    {
        $t = trim($raw);
        if (!$this->isRouteLike($t)) {
            return null;
        }
        if (preg_match('#^https?://#i', $t)) {
            return ['href' => $t, 'external' => true];
        }
        $path = str_starts_with($t, '/') ? $t : '/' . $t;
        $base = rtrim((string) config('manual_usuario.front_public_url', ''), '/');
        if ($base !== '') {
            return ['href' => $base . $path, 'external' => true];
        }

        return ['href' => $path, 'external' => false];
    }

    private function linkedTitleHtml(string $titulo, string $routeCandidate, string $cssClass): string
    {
        $link = $this->parseManualRoute($routeCandidate);
        if ($link) {
            return '<div class="' . $cssClass . '"><a class="title-link" href="' . e($link['href']) . '">'
                . e($titulo) . '</a></div>';
        }

        return '<div class="' . $cssClass . '">' . e($titulo) . '</div>';
    }

    /**
     * @param  mixed  $value
     */
    private function scalarForPdf($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            $flat = [];
            foreach ($value as $v) {
                if (is_scalar($v) || $v === null) {
                    $flat[] = (string) ($v ?? '');
                }
            }

            return implode(' · ', array_filter($flat, fn ($s) => $s !== ''));
        }

        return '';
    }
}
