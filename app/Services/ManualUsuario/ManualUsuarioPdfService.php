<?php

namespace App\Services\ManualUsuario;

use App\Support\BrandLogoPaths;
use Dompdf\Dompdf;
use Dompdf\Options;

class ManualUsuarioPdfService
{
    public function __construct(
        private ManualUsuarioCatalogService $catalog,
        private ManualUsuarioDbService $db
    ) {
    }

    public function renderRolePdf(string $slug): string
    {
        $role = $this->catalog->findRoleBySlug($slug);
        if (!$role) {
            throw new \InvalidArgumentException("Rol no encontrado: {$slug}");
        }

        $pages = $this->db->pagesForRole($slug);
        $chapters = array_map(fn (array $page) => [
            'title' => (string) ($page['titulo'] ?? ''),
            'html' => $this->db->pageToHtml($page),
        ], $pages);

        $html = view('manual-usuario.pdf', [
            'mode' => 'role',
            'title' => 'Manual de usuario',
            'subtitle' => 'Guía operativa — Rol: ' . ($role['nombre'] ?? $slug),
            'generatedAt' => now('America/Lima')->format('d/m/Y H:i'),
            'logoDataUri' => $this->logoDataUri(),
            'roles' => [[
                'role' => array_merge($role, ['meta' => $this->catalog->roleMeta($slug)]),
                'chapters' => $chapters,
                'toc' => $this->db->pagesToToc($pages),
            ]],
        ])->render();

        return $this->dompdf($html);
    }

    public function renderGlobalPdf(): string
    {
        $index = $this->catalog->index();
        $byRole = $this->db->pagesGroupedByRole();
        $roles = [];

        foreach ($this->catalog->roles() as $role) {
            $slug = (string) ($role['slug'] ?? '');
            if ($slug === '' || empty($byRole[$slug])) {
                continue;
            }
            $pages = $byRole[$slug];
            $roles[] = [
                'role' => array_merge($role, ['meta' => $this->catalog->roleMeta($slug)]),
                'chapters' => array_map(fn (array $page) => [
                    'title' => (string) ($page['titulo'] ?? ''),
                    'html' => $this->db->pageToHtml($page),
                ], $pages),
                'toc' => $this->db->pagesToToc($pages),
            ];
        }

        $html = view('manual-usuario.pdf', [
            'mode' => 'global',
            'title' => $index['title'] ?? 'Manual de usuario',
            'subtitle' => $index['description'] ?? 'Documentación por roles — compilación completa',
            'generatedAt' => now('America/Lima')->format('d/m/Y H:i'),
            'logoDataUri' => $this->logoDataUri(),
            'roles' => $roles,
        ])->render();

        return $this->dompdf($html);
    }

    private function logoDataUri(): ?string
    {
        foreach (['logo_probusiness.png', 'logo.png', 'probusiness.png', 'logo_white.png'] as $file) {
            $path = BrandLogoPaths::resolve($file);
            if (!$path || !is_readable($path)) {
                continue;
            }
            $bin = @file_get_contents($path);
            if ($bin === false || $bin === '') {
                continue;
            }
            $mime = @mime_content_type($path) ?: 'image/png';

            return 'data:' . $mime . ';base64,' . base64_encode($bin);
        }

        return null;
    }

    private function dompdf(string $html): string
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
        @ini_set('memory_limit', '512M');

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $chroot = public_path();
        if (is_dir($this->catalog->basePath())) {
            $chroot = $this->catalog->basePath();
        }
        $options->setChroot($chroot);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->embedRemoteHttpsImages($this->embedLocalImages($html)));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();
        if ($output === '' || $output === false) {
            throw new \RuntimeException('DomPDF no generó contenido para el manual.');
        }

        return $output;
    }

    private function embedLocalImages(string $html): string
    {
        $html = preg_replace_callback(
            '/src="([^"]*manual-usuario\/assets\/([^"]+))"/i',
            function ($m) {
                $relative = urldecode($m[2]);
                $absolute = $this->catalog->resolveAssetAbsolutePath($relative);
                if (!$absolute) {
                    return $m[0];
                }

                return $this->toDataUriSrc($absolute, $m[0]);
            },
            $html
        ) ?? $html;

        return preg_replace_callback(
            '/src="([^"]*manual-usuario\/media\/(\d+))"/i',
            function ($m) {
                $absolute = $this->db->absoluteMediaPathForPdf((int) $m[2]);
                if (!$absolute) {
                    return $m[0];
                }

                return $this->toDataUriSrc($absolute, $m[0]);
            },
            $html
        ) ?? $html;
    }

    private function embedRemoteHttpsImages(string $html): string
    {
        return preg_replace_callback(
            '/src="(https?:\/\/[^"]+)"/i',
            function ($m) {
                $url = $m[1];
                if (str_starts_with($url, 'data:')) {
                    return $m[0];
                }
                try {
                    $ctx = stream_context_create([
                        'http' => ['timeout' => 8, 'follow_location' => 1],
                        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                    ]);
                    $bin = @file_get_contents($url, false, $ctx);
                    if ($bin === false || $bin === '') {
                        return $m[0];
                    }
                    $mime = 'image/png';
                    if (preg_match('/\\.(jpe?g)(\\?|$)/i', $url)) {
                        $mime = 'image/jpeg';
                    } elseif (preg_match('/\\.webp(\\?|$)/i', $url)) {
                        $mime = 'image/webp';
                    } elseif (preg_match('/\\.gif(\\?|$)/i', $url)) {
                        $mime = 'image/gif';
                    }

                    return 'src="data:' . $mime . ';base64,' . base64_encode($bin) . '"';
                } catch (\Throwable $e) {
                    return $m[0];
                }
            },
            $html
        ) ?? $html;
    }

    private function toDataUriSrc(string $absolute, string $fallback): string
    {
        $mime = mime_content_type($absolute) ?: 'image/png';
        $data = base64_encode((string) file_get_contents($absolute));

        return 'src="data:' . $mime . ';base64,' . $data . '"';
    }
}
