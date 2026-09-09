<?php

namespace App\Console\Commands;

use App\Services\ManualUsuario\ManualUsuarioRoleManualSeeder;
use Illuminate\Console\Command;

class ManualSeedRolesCommand extends Command
{
    protected $signature = 'manual:seed-roles {--slug= : Solo un rol} {--key= : Solo una pantalla (ej. basedatos/productos). Sufijo * = prefijo}';

    protected $description = 'Siembra artículos CMS de la plantilla para los roles del menú';

    public function handle()
    {
        $slug = $this->option('slug') ?: null;
        $key = $this->option('key') ?: null;
        $result = (new ManualUsuarioRoleManualSeeder())->seed($slug, $key);

        $this->info('Páginas: ' . $result['pages']
            . ' (nuevas ' . $result['created']
            . ', actualizadas ' . $result['updated']
            . ', sin definición ' . $result['skipped'] . ').');

        return 0;
    }
}
