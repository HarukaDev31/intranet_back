<?php

namespace App\Services\ManualUsuario;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\Yaml\Yaml;

class ManualUsuarioCatalogService
{
    private string $basePath;

    private CommonMarkConverter $markdown;

    public function __construct()
    {
        $this->basePath = rtrim((string) config('manual_usuario.base_path', resource_path('manual')), DIRECTORY_SEPARATOR);
        $this->markdown = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function index(): array
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . 'index.yaml';
        if (!is_file($path)) {
            return [
                'version' => 1,
                'title' => 'Manual de usuario',
                'description' => '',
                'global' => [],
                'roles' => [],
            ];
        }

        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            return [
                'version' => 1,
                'title' => 'Manual de usuario',
                'description' => '',
                'global' => [],
                'roles' => [],
            ];
        }

        $data['global'] = $data['global'] ?? [];
        $data['roles'] = array_values($data['roles'] ?? []);

        return $data;
    }

    public function roles(): array
    {
        return $this->index()['roles'] ?? [];
    }

    public function findRoleBySlug(string $slug): ?array
    {
        foreach ($this->roles() as $role) {
            if (($role['slug'] ?? null) === $slug) {
                return $role;
            }
        }

        return null;
    }

    public function findRoleByGrupoId(int $idGrupo): ?array
    {
        foreach ($this->roles() as $role) {
            if ((int) ($role['id_grupo'] ?? 0) === $idGrupo) {
                return $role;
            }
        }

        return null;
    }

    public function roleMeta(string $slug): array
    {
        $metaPath = $this->roleDir($slug) . DIRECTORY_SEPARATOR . '_meta.yaml';
        if (!is_file($metaPath)) {
            return [];
        }

        $meta = Yaml::parseFile($metaPath);

        return is_array($meta) ? $meta : [];
    }

    public function listRoleChapters(string $slug): array
    {
        $dir = $this->roleDir($slug);
        if (!is_dir($dir)) {
            return [];
        }

        $files = collect(File::files($dir))
            ->filter(fn ($f) => Str::endsWith(strtolower($f->getFilename()), '.md'))
            ->sortBy(fn ($f) => $f->getFilename())
            ->values();

        $chapters = [];
        foreach ($files as $file) {
            $markdown = File::get($file->getPathname());
            // Ignorar stubs auto-generados antiguos
            if (str_contains($markdown, 'Describe en 1–2 frases')
                || str_contains($markdown, 'TODO captura')
                || str_contains($markdown, 'completar con el nombre real del botón')) {
                continue;
            }

            $relative = 'roles/' . $slug . '/' . $file->getFilename();
            $chapters[] = $this->chapterFromMarkdown($relative, $file->getPathname(), $slug);
        }

        return $chapters;
    }

    public function listGlobalChapters(): array
    {
        $index = $this->index();
        $chapters = [];
        foreach ($index['global'] ?? [] as $entry) {
            $relative = $entry['file'] ?? null;
            if (!$relative) {
                continue;
            }
            $absolute = $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (!is_file($absolute)) {
                continue;
            }
            $chapters[] = $this->chapterFromMarkdown($relative, $absolute, null);
        }

        return $chapters;
    }

    public function buildRoleManual(string $slug, bool $includeHtml = true): ?array
    {
        $role = $this->findRoleBySlug($slug);
        if (!$role) {
            return null;
        }

        $meta = $this->roleMeta($slug);
        $chapters = $this->listRoleChapters($slug);

        return [
            'role' => array_merge($role, [
                'meta' => $meta,
            ]),
            'chapters' => array_map(function (array $chapter) use ($includeHtml) {
                if (!$includeHtml) {
                    unset($chapter['html']);
                }

                return $chapter;
            }, $chapters),
        ];
    }

    public function buildGlobalManual(bool $includeHtml = true): array
    {
        $index = $this->index();
        $rolesPayload = [];
        foreach ($this->roles() as $role) {
            $slug = $role['slug'] ?? null;
            if (!$slug) {
                continue;
            }
            $manual = $this->buildRoleManual($slug, $includeHtml);
            if ($manual) {
                $rolesPayload[] = $manual;
            }
        }

        return [
            'title' => $index['title'] ?? 'Manual de usuario',
            'description' => $index['description'] ?? '',
            'global_chapters' => array_map(function (array $chapter) use ($includeHtml) {
                if (!$includeHtml) {
                    unset($chapter['html']);
                }

                return $chapter;
            }, $this->listGlobalChapters()),
            'roles' => $rolesPayload,
        ];
    }

    public function resolveAssetAbsolutePath(string $relativePath): ?string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        $absolute = $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $realBase = realpath($this->basePath);
        $realFile = realpath($absolute);

        if ($realBase === false || $realFile === false) {
            return null;
        }

        if (!str_starts_with($realFile, $realBase)) {
            return null;
        }

        if (!is_file($realFile)) {
            return null;
        }

        return $realFile;
    }

    public function roleDir(string $slug): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'roles' . DIRECTORY_SEPARATOR . $slug;
    }

    public function screenshotsDir(string $slug): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'screenshots' . DIRECTORY_SEPARATOR . $slug;
    }

    public function slugifyGrupoNombre(string $nombre): string
    {
        $slug = Str::slug($nombre, '-');
        if ($slug === '') {
            $slug = 'rol-' . substr(md5($nombre), 0, 8);
        }

        return $slug;
    }

    private function chapterFromMarkdown(string $relative, string $absolute, ?string $roleSlug): array
    {
        $markdown = File::get($absolute);
        $title = $this->extractTitle($markdown) ?: pathinfo($absolute, PATHINFO_FILENAME);
        $html = (string) $this->markdown->convert($markdown);
        $html = $this->rewriteImageUrls($html, $roleSlug);

        return [
            'id' => pathinfo($absolute, PATHINFO_FILENAME),
            'file' => str_replace('\\', '/', $relative),
            'title' => $title,
            'markdown' => $markdown,
            'html' => $html,
            'screenshots' => $this->extractScreenshots($markdown, $roleSlug),
        ];
    }

    private function extractTitle(string $markdown): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function extractScreenshots(string $markdown, ?string $roleSlug): array
    {
        $items = [];
        if (!preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $markdown, $matches, PREG_SET_ORDER)) {
            return $items;
        }

        foreach ($matches as $match) {
            $alt = $match[1];
            $src = $match[2];
            $relative = $this->normalizeAssetRelative($src, $roleSlug);
            if (!$relative || !$this->resolveAssetAbsolutePath($relative)) {
                continue;
            }
            $items[] = [
                'alt' => $alt,
                'src' => $src,
                'url' => url('/api/manual-usuario/assets/' . $relative),
            ];
        }

        return $items;
    }

    private function rewriteImageUrls(string $html, ?string $roleSlug): string
    {
        return preg_replace_callback(
            '/<img([^>]+)src="([^"]+)"[^>]*>/i',
            function ($m) use ($roleSlug) {
                $src = $m[2];
                if (Str::startsWith($src, ['http://', 'https://', 'data:', 'blob:'])) {
                    return $m[0];
                }

                $relative = $this->normalizeAssetRelative($src, $roleSlug);
                if (!$relative || !$this->resolveAssetAbsolutePath($relative)) {
                    // Sin archivo real: no emitir URL que termine en 404
                    return '';
                }

                return '<img' . $m[1] . 'src="' . e(url('/api/manual-usuario/assets/' . $relative)) . '">';
            },
            $html
        ) ?? $html;
    }

    private function normalizeAssetRelative(string $src, ?string $roleSlug): ?string
    {
        $src = trim($src);
        if ($src === '' || str_contains($src, '..')) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $src), './');

        if (Str::startsWith($normalized, 'screenshots/')) {
            return $normalized;
        }

        if ($roleSlug) {
            return 'screenshots/' . $roleSlug . '/' . basename($normalized);
        }

        return $normalized;
    }
}
