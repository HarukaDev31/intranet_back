<?php

namespace App\Console\Commands;

use App\Services\ManualUsuario\ManualUsuarioFrontWidgetScanner;
use Illuminate\Console\Command;

class ManualScanFrontWidgetsCommand extends Command
{
    protected $signature = 'manual:scan-front-widgets
        {--write : Escribe config/manual_usuario_page_widgets.php}
        {--backup : Guarda .bak antes de escribir (default true con --write)}
        {--no-backup : No crear backup}
        {--front= : Ruta absoluta al repo front (Nuxt)}
        {--only= : Prefijo relativo pages/ (ej: pages/cargaconsolidada/abiertos)}
        {--json : Imprime JSON del catálogo}';

    protected $description = 'Escanea pages/components Vue del front y regenera el catálogo de widgets (tablas/filtros/tabs)';

    public function handle(ManualUsuarioFrontWidgetScanner $scanner): int
    {
        $front = $this->option('front') ?: null;
        $only = $this->option('only') ?: null;

        try {
            $result = $scanner->scan($front, $only);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $pages = $result['pages'];
        $stats = $result['stats'];

        $this->info('Front: ' . ($result['front_path'] ?? ''));
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Pages escaneadas', $stats['pages_scanned']],
                ['Pages con widgets', $stats['pages_with_widgets']],
                ['Widgets', $stats['widgets']],
                ['DataTables', $stats['datatables']],
                ['Filtros', $stats['filters']],
            ]
        );

        if ($this->option('json')) {
            $this->line(json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            foreach (array_slice($pages, 0, 15) as $page) {
                $this->line(sprintf(
                    '• %s (%s) — %d widgets',
                    $page['key'],
                    $page['label'],
                    count($page['widgets'] ?? [])
                ));
            }
            if (count($pages) > 15) {
                $this->line('… +' . (count($pages) - 15) . ' pages');
            }
        }

        if ($this->option('write')) {
            $backup = !$this->option('no-backup');
            if ($only) {
                $pages = $scanner->mergeIntoExistingConfig($pages);
                $this->comment('Merge parcial (--only): se actualizaron las pages del prefijo sin borrar el resto.');
            }
            $path = $scanner->writeConfig($pages, $backup);
            $this->info('Escrito: ' . $path);
            $this->comment('Reinicia config cache si aplica: php artisan config:clear');
        } else {
            $this->comment('Dry-run. Usa --write para persistir el catálogo.');
        }

        return self::SUCCESS;
    }
}
