<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class WaCopilotoImportSalesContextCommand extends Command
{
    protected $signature = 'wa-copiloto:import-sales-context
                            {source? : Ruta del TXT de conversaciones WON}
                            {--clear-cache : Limpia cache del contexto de ventas}';

    protected $description = 'Importa el TXT de conversaciones WON a storage/app para contexto Gemini del Copiloto';

    public function handle()
    {
        if ($this->option('clear-cache')) {
            Cache::forget('wa_copiloto_sales_knowledge_v3');
            $this->info('Cache wa_copiloto_sales_knowledge_v2 limpiada.');
        }

        $target = (string) config('meta_whatsapp_copiloto.analysis_sales_context_path', 'ventas_contexto.txt');
        $source = (string) ($this->argument('source') ?: storage_path('app/conversaciones_WON.csv'));

        if (!is_file($source)) {
            $this->error('Archivo origen no encontrado: ' . $source);
            $this->line('Uso: php artisan wa-copiloto:import-sales-context "C:\\ruta\\ventas_120deals.txt"');

            return 1;
        }

        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if ($ext !== 'txt') {
            $this->warn('El origen no es .txt (' . $ext . '). Se copiará igualmente.');
        }

        $contents = file_get_contents($source);
        if ($contents === false || trim($contents) === '') {
            $this->error('No se pudo leer el archivo o está vacío.');

            return 1;
        }

        Storage::disk('local')->put($target, $contents);
        Cache::forget('wa_copiloto_sales_knowledge_v3');

        $bytes = strlen($contents);
        $lines = substr_count($contents, "\n") + 1;
        $this->info('Contexto importado → storage/app/' . $target);
        $this->line('Tamaño: ' . number_format($bytes) . ' bytes · ' . number_format($lines) . ' líneas');

        return 0;
    }
}
