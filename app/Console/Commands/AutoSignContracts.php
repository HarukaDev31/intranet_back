<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoSignContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:auto-sign';

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
        $this->info('Buscando cotizaciones para auto-firmar contratos...');

        // Buscar cotizaciones donde fecha_confirmacion + 2 días <= ahora y autosigned_contract_at es null
        $twoDaysAgo = Carbon::now()->subDays(2);
        
        $cotizaciones = Cotizacion::whereNotNull('fecha_confirmacion')
            ->where('fecha_confirmacion', '<=', $twoDaysAgo)
            ->whereNull('estado_cliente')
            ->where('cotizacion_contrato_firmado_url', '=', null)
            ->where('cotizacion_contrato_autosigned_url', '=', null)
            ->where('estado_cotizador', '=', 'CONFIRMADO')
            ->whereNotNull('cod_contract')
            ->whereNotNull('uuid')
            ->get();

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

    /**
     * Genera un contrato auto-firmado para una cotización
     *
     * @param Cotizacion $cotizacion
     * @return void
     */
    private function generateAutoSignedContract(Cotizacion $cotizacion)
    {
        // Crear directorio si no existe
        $contratosDir = public_path('storage/contratos');
        if (!file_exists($contratosDir)) {
            mkdir($contratosDir, 0755, true);
        }

        // Generar nombre del archivo PDF
        $pdfFilename = $cotizacion->uuid . '_autosigned_contract.pdf';
        $pdfPath = $contratosDir . '/' . $pdfFilename;

        // Eliminar archivo anterior si existe
        if (file_exists($pdfPath)) {
            unlink($pdfPath);
        }

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

        // Guardar PDF
        file_put_contents($pdfPath, $pdfContent);

        // Guardar ruta relativa en la base de datos
        $relativePath = 'contratos/' . $pdfFilename;
        
        DB::table('contenedor_consolidado_cotizacion')
            ->where('id', $cotizacion->id)
            ->update([
                'autosigned_contract_at' => now(),
                'cotizacion_contrato_autosigned_url' => $relativePath
            ]);

        Log::info('Contrato auto-firmado guardado exitosamente', [
            'cotizacion_id' => $cotizacion->id,
            'filename' => $pdfFilename,
            'relative_path' => $relativePath
        ]);
    }
}
