<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RotuladoDownloadIconsCommand extends Command
{
    protected $signature = 'rotulado:download-icons';

    protected $description = 'Genera los iconos PNG del rotulado (DomPDF no soporta SVG complejos)';

    /**
     * @return int
     */
    public function handle()
    {
        $script = base_path('scripts/generate_rotulado_icons.php');
        if (!is_file($script)) {
            $this->error('No se encontró scripts/generate_rotulado_icons.php');

            return self::FAILURE;
        }

        passthru(PHP_BINARY . ' ' . escapeshellarg($script), $exitCode);

        if ($exitCode !== 0) {
            $this->error('Error generando iconos PNG');

            return self::FAILURE;
        }

        $this->info('Iconos PNG generados en public/assets/templates/rotulado_icons/');

        return self::SUCCESS;
    }
}
