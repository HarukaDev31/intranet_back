<?php

namespace App\Services\ManualUsuario;

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
        $chapters = array_map(function (array $page) {
            return [
                'html' => $this->db->pageToHtml($page),
            ];
        }, $pages);

        $html = view('manual-usuario.pdf', [
            'mode' => 'role',
            'title' => 'Manual — ' . ($role['nombre'] ?? $slug),
            'subtitle' => 'Rol: ' . ($role['nombre'] ?? $slug),
            'generatedAt' => now('America/Lima')->format('d/m/Y H:i'),
            'globalChapters' => [],
            'roles' => [[
                'role' => $role,
                'chapters' => $chapters,
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
            $roles[] = [
                'role' => $role,
                'chapters' => array_map(function (array $page) {
                    return ['html' => $this->db->pageToHtml($page)];
                }, $byRole[$slug]),
            ];
        }

        $html = view('manual-usuario.pdf', [
            'mode' => 'global',
            'title' => $index['title'] ?? 'Manual de usuario',
            'subtitle' => $index['description'] ?? 'Documentación por roles (CMS)',
            'generatedAt' => now('America/Lima')->format('d/m/Y H:i'),
            'globalChapters' => [],
            'roles' => $roles,
        ])->render();

        return $this->dompdf($html);
    }

    private function dompdf(string $html): string
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
        @ini_set('memory_limit', '512M');

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot($this->catalog->basePath());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->embedLocalImages($html));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();
        if ($output === '' || $output === false) {
            throw new \RuntimeException('DomPDF no generó contenido para el manual.');
        }

        return $output;
    }

    /**
     * Convierte URLs /api/manual-usuario/assets/... y /media/{id} a data URI para DomPDF.
     */
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

    private function toDataUriSrc(string $absolute, string $fallback): string
    {
        $mime = mime_content_type($absolute) ?: 'image/png';
        $data = base64_encode((string) file_get_contents($absolute));

        return 'src="data:' . $mime . ';base64,' . $data . '"';
    }
}
