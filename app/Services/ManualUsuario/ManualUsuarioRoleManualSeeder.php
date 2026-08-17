<?php

namespace App\Services\ManualUsuario;

/**
 * Siembra artículos CMS por rol según menú + plantilla.
 */
class ManualUsuarioRoleManualSeeder
{
    /**
     * @param  string|null $onlySlug
     * @param  string|null $onlyKey
     * @return array{created:int, updated:int, skipped:int, pages:int}
     */
    public function seed($onlySlug = null, $onlyKey = null)
    {
        $catalog = new ManualUsuarioScreensCatalog();
        $writer = new ManualUsuarioArticuloWriter();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $pages = 0;

        foreach ($catalog->roles() as $role) {
            if ($onlySlug && $role['slug'] !== $onlySlug) {
                continue;
            }
            $orden = 1;
            foreach ($role['screens'] as $key) {
                if ($onlyKey && !$this->keyMatches($key, $onlyKey)) {
                    $orden++;
                    continue;
                }
                $screen = $catalog->screen($key);
                if (!$screen) {
                    $skipped++;
                    continue;
                }
                $result = $writer->seed($role, $screen, $onlyKey ? $this->keepOrden($role, $key, $orden) : $orden);
                $orden++;
                $pages++;
                if ($result['created']) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        }

        return compact('created', 'updated', 'skipped', 'pages');
    }

    private function keyMatches($key, $onlyKey)
    {
        $onlyKey = (string) $onlyKey;
        if ($onlyKey === '') {
            return true;
        }
        if (substr($onlyKey, -1) === '*') {
            $prefix = substr($onlyKey, 0, -1);
            return strpos((string) $key, $prefix) === 0;
        }
        return (string) $key === $onlyKey;
    }

    private function keepOrden(array $role, $key, $fallback)
    {
        $page = \App\Models\ManualUsuario\ManualPagina::query()
            ->where('role_slug', $role['slug'])
            ->where('modulo_key', $key)
            ->first();

        return $page && $page->orden ? (int) $page->orden : $fallback;
    }
}
