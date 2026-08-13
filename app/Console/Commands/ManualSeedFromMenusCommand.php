<?php

namespace App\Console\Commands;

use App\Services\ManualUsuario\ManualUsuarioCatalogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class ManualSeedFromMenusCommand extends Command
{
    protected $signature = 'manual:seed-from-menus
                            {--force : Sobrescribe stubs existentes}
                            {--write-index : Regenera resources/manual/index.yaml}';

    protected $description = 'Genera stubs Markdown del manual por rol a partir de los menús del sidebar';

    public function handle(ManualUsuarioCatalogService $catalog): int
    {
        $excluded = config('manual_usuario.excluded_grupo_ids', [1205]);
        $force = (bool) $this->option('force');
        $writeIndex = (bool) $this->option('write-index');

        $grupos = DB::table('grupo')
            ->whereNotIn('ID_Grupo', $excluded)
            ->orderBy('ID_Grupo')
            ->get(['ID_Grupo', 'No_Grupo', 'No_Grupo_Descripcion']);

        if ($grupos->isEmpty()) {
            $this->error('No se encontraron grupos.');

            return 1;
        }

        $rolesForIndex = [];
        $created = 0;
        $skipped = 0;

        foreach ($grupos as $grupo) {
            $idGrupo = (int) $grupo->ID_Grupo;
            $nombre = (string) $grupo->No_Grupo;
            $slug = $catalog->slugifyGrupoNombre($nombre);

            // Preferir slug del index si ya existe para este ID_Grupo
            $existing = $catalog->findRoleByGrupoId($idGrupo);
            if ($existing && !empty($existing['slug'])) {
                $slug = $existing['slug'];
            }

            $roleDir = $catalog->roleDir($slug);
            $shotsDir = $catalog->screenshotsDir($slug);
            File::ensureDirectoryExists($roleDir);
            File::ensureDirectoryExists($shotsDir);

            $menus = $this->menusForGrupo($idGrupo);

            $meta = [
                'slug' => $slug,
                'id_grupo' => $idGrupo,
                'nombre' => $nombre,
                'descripcion' => $grupo->No_Grupo_Descripcion ?: ('Manual del rol ' . $nombre),
                'menus' => array_map(function ($m) {
                    return [
                        'id_menu' => (int) $m->ID_Menu,
                        'titulo' => $m->No_Menu,
                        'url' => $m->url_intranet_v2 ?: $m->No_Menu_Url,
                    ];
                }, $menus),
            ];

            $metaPath = $roleDir . DIRECTORY_SEPARATOR . '_meta.yaml';
            $existingMeta = is_file($metaPath) ? Yaml::parseFile($metaPath) : [];
            if (!is_array($existingMeta)) {
                $existingMeta = [];
            }
            $isCurated = (bool) ($existingMeta['curated'] ?? false);

            if ($force || !is_file($metaPath)) {
                if ($isCurated) {
                    $meta['curated'] = true;
                    $meta['descripcion'] = $existingMeta['descripcion'] ?? $meta['descripcion'];
                }
                File::put($metaPath, Yaml::dump($meta, 4, 2));
            }

            if ($isCurated && !$force) {
                $this->line("Rol {$nombre} ({$slug}): curated — no se regeneran capítulos");
                $rolesForIndex[] = [
                    'slug' => $slug,
                    'id_grupo' => $idGrupo,
                    'nombre' => $nombre,
                ];
                continue;
            }

            $order = 1;
            foreach ($menus as $menu) {
                $url = trim((string) ($menu->url_intranet_v2 ?: $menu->No_Menu_Url ?: ''));
                if ($this->shouldSkipMenu($menu, $url)) {
                    continue;
                }

                $fileSlug = $catalog->slugifyGrupoNombre($url !== '' ? $url : (string) $menu->No_Menu);
                if ($fileSlug === '') {
                    $fileSlug = 'menu-' . $menu->ID_Menu;
                }
                $filename = str_pad((string) $order, 2, '0', STR_PAD_LEFT) . '-' . $fileSlug . '.md';
                $path = $roleDir . DIRECTORY_SEPARATOR . $filename;

                if (!$force && is_file($path)) {
                    $skipped++;
                    $order++;
                    continue;
                }

                $content = $this->stubMarkdown(
                    (string) $menu->No_Menu,
                    $url
                );
                File::put($path, $content);
                $created++;
                $order++;
            }

            // Placeholder .gitkeep en screenshots
            $gitkeep = $shotsDir . DIRECTORY_SEPARATOR . '.gitkeep';
            if (!is_file($gitkeep)) {
                File::put($gitkeep, '');
            }

            $rolesForIndex[] = [
                'slug' => $slug,
                'id_grupo' => $idGrupo,
                'nombre' => $nombre,
            ];

            $this->line("Rol {$nombre} ({$slug}): " . count($menus) . ' menús');
        }

        if ($writeIndex) {
            $indexPath = $catalog->basePath() . DIRECTORY_SEPARATOR . 'index.yaml';
            $current = is_file($indexPath) ? Yaml::parseFile($indexPath) : [];
            if (!is_array($current)) {
                $current = [];
            }

            $index = [
                'version' => (int) ($current['version'] ?? 1),
                'title' => $current['title'] ?? 'Manual de usuario — Intranet Probusiness',
                'description' => $current['description'] ?? 'Guías por rol con capturas y pasos en lenguaje de usuario.',
                'global' => $current['global'] ?? [
                    ['file' => 'global/00-introduccion.md'],
                    ['file' => 'global/01-inicio-sesion.md'],
                ],
                'roles' => $rolesForIndex,
            ];
            File::put($indexPath, Yaml::dump($index, 4, 2));
            $this->info('index.yaml actualizado.');
        }

        $this->info("Listo. Stubs creados: {$created}. Omitidos (ya existían): {$skipped}.");

        return 0;
    }

    /**
     * Menús padre activos visibles para un ID_Grupo (misma idea que MenuController).
     */
    private function menusForGrupo(int $idGrupo): array
    {
        $sql = "SELECT DISTINCT
                    MNU.ID_Menu,
                    MNU.No_Menu,
                    MNU.No_Menu_Url,
                    MNU.url_intranet_v2,
                    MNU.Nu_Orden
                FROM menu AS MNU
                JOIN menu_acceso AS MNUACCESS ON (MNU.ID_Menu = MNUACCESS.ID_Menu)
                JOIN grupo_usuario AS GRPUSR ON (GRPUSR.ID_Grupo_Usuario = MNUACCESS.ID_Grupo_Usuario)
                WHERE MNU.ID_Padre = 0
                AND MNU.Nu_Activo = 0
                AND GRPUSR.ID_Grupo = ?
                ORDER BY MNU.Nu_Orden, MNU.ID_Menu";

        $padres = DB::select($sql, [$idGrupo]);
        $result = [];

        foreach ($padres as $padre) {
            $hijos = DB::select(
                "SELECT DISTINCT
                    MNU.ID_Menu,
                    MNU.No_Menu,
                    MNU.No_Menu_Url,
                    MNU.url_intranet_v2,
                    MNU.Nu_Orden
                FROM menu AS MNU
                JOIN menu_acceso AS MNUACCESS ON (MNU.ID_Menu = MNUACCESS.ID_Menu)
                JOIN grupo_usuario AS GRPUSR ON (GRPUSR.ID_Grupo_Usuario = MNUACCESS.ID_Grupo_Usuario)
                WHERE MNU.ID_Padre = ?
                AND MNU.Nu_Activo = 0
                AND GRPUSR.ID_Grupo = ?
                ORDER BY MNU.Nu_Orden, MNU.ID_Menu",
                [$padre->ID_Menu, $idGrupo]
            );

            // Si hay hijos, documentamos los hijos (evita duplicar el padre vacío)
            if (count($hijos) > 0) {
                foreach ($hijos as $hijo) {
                    $hijo->No_Menu = $padre->No_Menu . ' → ' . $hijo->No_Menu;
                    $result[] = $hijo;
                }
            } else {
                $result[] = $padre;
            }
        }

        return $result;
    }

    private function shouldSkipMenu(object $menu, string $url): bool
    {
        $titulo = mb_strtolower((string) ($menu->No_Menu ?? ''));
        $urlNorm = mb_strtolower(trim($url, '/#'));

        if ($urlNorm === 'manual-usuario' || str_contains($titulo, 'manual de usuario')) {
            return true;
        }

        if ($urlNorm === '' || $urlNorm === '#') {
            // Padres sin ruta útil ya se filtran si tienen hijos; si queda uno solo, lo saltamos
            return true;
        }

        return false;
    }

    private function stubMarkdown(string $titulo, string $url): string
    {
        $ruta = $url !== '' ? "Ruta en el menú: **{$titulo}**." : "Entra desde el menú a **{$titulo}**.";

        return <<<MD
# {$titulo}

## Para qué sirve

Explica aquí, en 1 o 2 frases sencillas, qué logra el usuario en esta pantalla (sin jerga técnica).

## Cómo entrar

{$ruta}

## Paso a paso

1. Abre la pantalla desde el menú.
2. Usa los filtros o la búsqueda si hay muchos registros.
3. Indica el botón principal que debe pulsar (ej. Crear, Guardar, Ver detalle).
4. Si hay pestañas o ventanas emergentes, describe cada una con el nombre que ve en pantalla.

## Qué ocurre después

Cuenta el resultado que el usuario ve: cambio de estado, mensaje de confirmación, archivo generado, etc.

## Si algo no funciona

- Faltan datos obligatorios
- No tiene permiso para esa acción
- El registro no aparece por filtros activos

> Cuando tengas la captura, colócala en `resources/manual/screenshots/...` y enlázala aquí.

MD;
    }
}
