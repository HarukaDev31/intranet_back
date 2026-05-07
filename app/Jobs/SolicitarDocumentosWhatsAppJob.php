<?php

namespace App\Jobs;

use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Services\CargaConsolidada\Clientes\ExcelConfirmacionDocumentosService;
use App\Traits\WhatsappTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SolicitarDocumentosWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait;

    /** @var int */
    protected $idCotizacion;

    /** @var array */
    protected $proveedores;

    /** @var bool */
    protected $validateMaxDate;

    /**
     * @param  array<int, array<string, mixed>>  $proveedores
     */
    public function __construct(int $idCotizacion, array $proveedores, bool $validateMaxDate = false)
    {
        $this->idCotizacion = $idCotizacion;
        $this->proveedores = $proveedores;
        $this->validateMaxDate = $validateMaxDate;
        $this->onQueue('importaciones');
    }

    public function handle(ExcelConfirmacionDocumentosService $excelService): void
    {
        try {
            $cot = DB::table('contenedor_consolidado_cotizacion')
                ->select('id_contenedor', 'telefono')
                ->where('id', $this->idCotizacion)
                ->whereNull('deleted_at')
                ->first();

            if (!$cot) {
                Log::warning('SolicitarDocumentosWhatsAppJob: cotización no encontrada', ['id_cotizacion' => $this->idCotizacion]);

                return;
            }

            $telefono = preg_replace('/\s+/', '', $cot->telefono ?? '');
            $telefono = $telefono !== '' ? $telefono . '@c.us' : '';

            if ($telefono === '') {
                Log::warning('SolicitarDocumentosWhatsAppJob: sin teléfono', ['id_cotizacion' => $this->idCotizacion]);

                return;
            }

            $cont = DB::table('carga_consolidada_contenedor')
                ->select('carga')
                ->where('id', $cot->id_contenedor)
                ->first();

            $carga = $cont->carga ?? '';
            $cargaCode = is_numeric($carga) ? str_pad((string) $carga, 2, '0', STR_PAD_LEFT) : $carga;

            $message = "⚠️IMPORTANTE⚠️\n\nEl siguiente paso es la recopilación de tus documentos para la declaración en Aduanas. Para ello, te solicitaré los siguientes documento.\n\nDocumentación: CONSOLIDADO #{$cargaCode}\n\n☑ PASO 1: Llenar el Excel de confirmación con las características de los productos que estás importando para poder declarar correctamente tus productos 📄 y evitar multas o pérdidas en aduanas.\n\n📢 IMPORTANTE:  Ver el video sobre el Excel de confirmación. 📋\n\nVideo:  https://youtu.be/rvhwblBEbXQ";

            $response = $this->sendMessage($message, $telefono, 5);
            Log::info('SolicitarDocumentosWhatsAppJob paso 1 WhatsApp: ' . json_encode($response));

            $templatePath = storage_path('app/public/templates/excel-confirmacion/EXCEL_DE_CONFIRMACION_GENERAL.xlsx');
            $outputDir = storage_path('app/public/excel-confirmacion');
            if (!is_dir($outputDir)) {
                @mkdir($outputDir, 0775, true);
            }

            foreach ($this->proveedores as $prov) {
                $cotizacionProveedor = CotizacionProveedor::where('id', $prov['id'] ?? null)->first();
                $codeSupplier = $cotizacionProveedor ? (string) ($cotizacionProveedor->code_supplier ?? '') : '';
                $suffixArchivo = $codeSupplier !== '' ? $codeSupplier : ('prov_' . ($prov['id'] ?? 'unknown'));
                $fileName = 'excel_confirmacion' . '_' . $suffixArchivo . '.xlsx';
                $fullPath = $outputDir . DIRECTORY_SEPARATOR . $fileName;

                $ok = $excelService->generarArchivoPorProveedor($templatePath, $fullPath, $prov);
                if (!$ok) {
                    continue;
                }

                $response = $this->sendMedia($fullPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Documento de confirmación', $telefono, 5);
                Log::info('SolicitarDocumentosWhatsAppJob excel WhatsApp: ' . json_encode($response));
            }

            $contenedor = Contenedor::where('id', $cot->id_contenedor)->first();
            $fecha_documentacion_max = $contenedor ? $contenedor->fecha_documentacion_max : null;
            $fecha_documentacion_max_formatted = $fecha_documentacion_max ? date('d/m/Y', strtotime($fecha_documentacion_max)) : null;

            $messagePaso2 = "☑ PASO 2: Solicita a tu proveedor los documentos finales:
•⁠  ⁠Commercial Invoice 📄.
•⁠  ⁠Packing List 📦.

📋 Adjuntamos un Word con indicaciones para un correcto llenado.
📩 El documento está en idioma chino, solo enviarlo a su proveedor.
🚫 Indicar a tu proveedor, que no se rellena encima del World . ESTE WORD ES SOLO UNA GUIA.

";
            if ($this->validateMaxDate && $fecha_documentacion_max_formatted) {
                $messagePaso2 .= "Fecha maxima de entrega: {$fecha_documentacion_max_formatted}";
            }

            $this->sendMessage($messagePaso2, $telefono, 8);

            $considerationsPath = public_path('storage/templates/CONSIDERATIONS.docx');
            $response = $this->sendMedia($considerationsPath, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Consideraciones', $telefono, 10);
            Log::info('SolicitarDocumentosWhatsAppJob considerations: ' . json_encode($response));
        } catch (\Throwable $e) {
            Log::error('SolicitarDocumentosWhatsAppJob error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}
