<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\UsesObjectStorage;

class AutoSignContracts extends Command
{
    use UsesObjectStorage;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:auto-sign
        {--repair-missing : Regenerar PDF cuando hay ruta en BD pero el archivo no existe en storage}
        {--regenerate-all-autosigned : Regenerar todos los que tienen autosigned_contract_at y subirlos a S3}
        {--id= : Solo procesar esta cotización (id numérico)}
        {--limit=0 : Máximo de registros a procesar (0 = sin límite)}
        {--dry-run : Listar cotizaciones a procesar sin generar PDF}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera contratos automáticamente para cotizaciones confirmadas hace 2 o más días';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info('Iniciando proceso de auto-firma de contratos');
        $dryRun = (bool) $this->option('dry-run');

        if ($this->option('repair-missing')) {
            return $this->repairMissingAutosigned($dryRun);
        }

        if ($this->option('regenerate-all-autosigned')) {
            return $this->regenerateAllAutosigned($dryRun);
        }

        $this->info('Buscando cotizaciones para auto-firmar contratos...');

        // Buscar cotizaciones donde fecha_confirmacion + 2 días <= ahora y autosigned_contract_at es null
        $twoDaysAgo = Carbon::now()->subDays(2);

        $query = Cotizacion::whereNotNull('fecha_confirmacion')
            ->where('fecha_confirmacion', '<=', $twoDaysAgo)
            ->whereNull('estado_cliente')
            ->where('cotizacion_contrato_firmado_url', '=', null)
            ->where('cotizacion_contrato_autosigned_url', '=', null)
            ->where('estado_cotizador', '=', 'CONFIRMADO')
            ->whereNotNull('cod_contract')
            ->whereNotNull('uuid');

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        }

        $cotizaciones = $query->get();

        $this->info("Encontradas {$cotizaciones->count()} cotizaciones para procesar");

        $processed = 0;
        $errors = 0;

        foreach ($cotizaciones as $cotizacion) {
            try {
                $this->generateAutoSignedContract($cotizacion);
                $processed++;
                $this->info("Contrato generado para cotización ID: {$cotizacion->id}");
            } catch (\Exception $e) {
                $errors++;
                $this->error("Error al generar contrato para cotización ID {$cotizacion->id}: " . $e->getMessage());
                Log::error('Error en AutoSignContracts para cotización ' . $cotizacion->id, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info("Procesadas: {$processed}, Errores: {$errors}");

        return 0;
    }

    private function repairMissingAutosigned(bool $dryRun): int
    {
        $this->info('Buscando autosigned en BD sin archivo en storage...');

        $query = Cotizacion::query()
            ->whereNotNull('cotizacion_contrato_autosigned_url')
            ->whereNotNull('uuid');

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        }

        $cotizaciones = $query->get();
        $toRepair = $cotizaciones->filter(function (Cotizacion $cotizacion) {
            $uploadPath = $this->storageUploadPathFromDb($cotizacion->cotizacion_contrato_autosigned_url);

            if ($uploadPath === null) {
                return true;
            }

            if ($this->objectStorage()->uploadDisk() === 's3') {
                return !$this->objectStorage()->existsOnS3($uploadPath);
            }

            return !$this->objectStorage()->exists($uploadPath);
        });

        $this->info("Con ruta en BD: {$cotizaciones->count()}, sin archivo real: {$toRepair->count()}");

        if ($toRepair->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($toRepair as $cotizacion) {
            $this->line("  - ID {$cotizacion->id} | {$cotizacion->uuid} | BD: {$cotizacion->cotizacion_contrato_autosigned_url}");
        }

        if ($dryRun) {
            $this->warn('Dry-run: no se generó ningún PDF.');

            return self::SUCCESS;
        }

        $processed = 0;
        $errors = 0;

        foreach ($toRepair as $cotizacion) {
            try {
                $this->generateAutoSignedContract($cotizacion);
                $processed++;
                $this->info("Regenerado ID {$cotizacion->id}");
            } catch (\Exception $e) {
                $errors++;
                $this->error("Error ID {$cotizacion->id}: " . $e->getMessage());
                Log::error('Error reparando autosigned', [
                    'cotizacion_id' => $cotizacion->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Regenerados: {$processed}, Errores: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function regenerateAllAutosigned(bool $dryRun): int
    {
        $this->info('Regenerando todos los contratos con autosigned_contract_at...');

        $query = Cotizacion::query()
            ->whereNotNull('autosigned_contract_at')
            ->whereNotNull('uuid')
            ->orderBy('id');

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        }

        $limit = max(0, (int) $this->option('limit'));
        if ($limit > 0) {
            $query->limit($limit);
        }

        $cotizaciones = $query->get();
        $this->info("Cotizaciones a regenerar: {$cotizaciones->count()}");

        foreach ($cotizaciones as $cotizacion) {
            $this->line("  - ID {$cotizacion->id} | {$cotizacion->uuid} | {$cotizacion->autosigned_contract_at}");
        }

        if ($dryRun) {
            $this->warn('Dry-run: no se generó ningún PDF.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($cotizaciones->count());
        $bar->start();

        $processed = 0;
        $errors = 0;

        foreach ($cotizaciones as $cotizacion) {
            try {
                $this->generateAutoSignedContract($cotizacion);
                $processed++;
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Error ID {$cotizacion->id}: " . $e->getMessage());
                Log::error('Error regenerando autosigned', [
                    'cotizacion_id' => $cotizacion->id,
                    'error' => $e->getMessage(),
                ]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Regenerados: {$processed}, Errores: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Genera un contrato auto-firmado para una cotización
     *
     * @param Cotizacion $cotizacion
     * @return void
     */
    private function generateAutoSignedContract(Cotizacion $cotizacion)
    {
        $pdfFilename = $cotizacion->uuid . '_autosigned_contract.pdf';
        $relativePath = 'contratos/' . $pdfFilename;

        // Obtener información del contenedor
        $contenedor = Contenedor::find($cotizacion->id_contenedor);
        $carga = $contenedor ? $contenedor->carga : 'N/A';

        // Cargar imagen de auto-aceptación
        $autoSignImagePath = public_path('storage/auto_accept_sign.png');
        
        if (!file_exists($autoSignImagePath)) {
            throw new \Exception("No se encontró la imagen de auto-aceptación en: {$autoSignImagePath}");
        }

        // Convertir imagen a base64
        $imageData = base64_encode(file_get_contents($autoSignImagePath));
        $signatureBase64 = 'data:image/png;base64,' . $imageData;

        // Datos para la vista del contrato firmado
        $viewData = [
            'fecha' => date('d-m-Y'),
            'cliente_nombre' => $cotizacion->nombre,
            'cliente_documento' => $cotizacion->documento,
            'cliente_domicilio' => $cotizacion->direccion ?? null,
            'carga' => $carga,
            'logo_contrato_url' => public_path('storage/logo_contrato.png'),
            'signature_base64' => $signatureBase64,
            'cod_contract' => $cotizacion->cod_contract,
        ];

        // Renderizar vista del contrato con firma
        $contractHtml = view('contracts.contrato_firmado', $viewData)->render();

        // Configurar DomPDF
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        ini_set('memory_limit', '512M');

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($contractHtml);
        $dompdf->setPaper('A4', 'portrait');

        Log::info('Iniciando renderizado PDF auto-firmado para cotizacion ' . $cotizacion->id);
        $start = microtime(true);
        $dompdf->render();
        $duration = microtime(true) - $start;
        Log::info('Renderizado PDF auto-firmado completado en ' . round($duration, 2) . 's para cotizacion ' . $cotizacion->id);

        $pdfContent = $dompdf->output();

        if ($pdfContent === '' || $pdfContent === false) {
            throw new \RuntimeException('DomPDF no generó contenido para cotización ' . $cotizacion->id);
        }

        $cdnDbPath = $this->storagePutContentsForCdn($relativePath, $pdfContent);

        DB::table('contenedor_consolidado_cotizacion')
            ->where('id', $cotizacion->id)
            ->whereNull('deleted_at')
            ->update([
                'autosigned_contract_at' => now(),
                'cotizacion_contrato_autosigned_url' => $cdnDbPath,
            ]);

        Log::info('Contrato auto-firmado guardado exitosamente', [
            'cotizacion_id' => $cotizacion->id,
            'filename' => $pdfFilename,
            'upload_path' => $relativePath,
            'cdn_db_path' => $cdnDbPath,
            'upload_disk' => $this->objectStorage()->uploadDisk(),
        ]);
    }
}
