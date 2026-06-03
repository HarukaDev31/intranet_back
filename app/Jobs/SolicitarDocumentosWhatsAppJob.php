<?php

namespace App\Jobs;

use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Services\CargaConsolidada\Clientes\ExcelConfirmacionDocumentosService;
use App\Services\Google\GoogleDriveExcelConfirmacionService;
use App\Services\Storage\S3ObjectStorageConnector;
use App\Services\WhatsApp\WhatsAppCoordinacionBatchService;
use App\Support\WhatsApp\CoordinacionWhatsappPayload;
use App\Traits\DatabaseConnectionTrait;
use App\Traits\UsesObjectStorage;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UsesObjectStorage, WhatsappTrait, DatabaseConnectionTrait;

    /** Clave S3 bajo el bucket (ej. bucket probusiness-intranet → templates/CONSIDERATIONS.pdf) */
    private const S3_TEMPLATES_BASE = 'templates';

    /** @var int */
    protected $idCotizacion;

    /** @var array */
    protected $proveedores;

    /** @var bool */
    protected $validateMaxDate;

    /** @var string|null */
    protected $domain;

    /**
     * @param  array<int, array<string, mixed>>  $proveedores
     */
    public function __construct(int $idCotizacion, array $proveedores, bool $validateMaxDate = false, $domain = null)
    {
        $this->idCotizacion = $idCotizacion;
        $this->proveedores = $proveedores;
        $this->validateMaxDate = $validateMaxDate;
        $this->domain = $domain;
        $this->onQueue('importaciones');
    }

    public function handle(
        ExcelConfirmacionDocumentosService $excelService,
        GoogleDriveExcelConfirmacionService $driveExcelService
    ): void {
        try {
            $this->setDatabaseConnection($this->domain ?? 'localhost');

            $cot = DB::table('contenedor_consolidado_cotizacion')
                ->select('id_contenedor', 'telefono', 'nombre')
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
            $nombreCliente = trim((string) ($cot->nombre ?? ''));

            $contenedor = Contenedor::where('id', $cot->id_contenedor)->first();
            $fecha_documentacion_max = $contenedor ? $contenedor->fecha_documentacion_max : null;
            $fecha_documentacion_max_formatted = $fecha_documentacion_max ? date('d/m/Y', strtotime($fecha_documentacion_max)) : null;

            $messagePaso1 = "⚠️IMPORTANTE⚠️\n\nEl siguiente paso es la recopilación de tus documentos para la declaración en Aduanas. Para ello, te solicitaré los siguientes documento.\n\nDocumentación: CONSOLIDADO #{$cargaCode}\n\n☑ PASO 1: Llenar el Excel de confirmación con las características de los productos que estás importando para poder declarar correctamente tus productos 📄 y evitar multas o pérdidas en aduanas.\n\n📢 IMPORTANTE:  Ver el video sobre el Excel de confirmación. 📋\n\nVideo:  https://youtu.be/rvhwblBEbXQ";

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

            $considerationsFile = $this->resolveConsiderationsFile();

            /** @var array<int, array<string, mixed>> $steps */
            $steps = [
                ['type' => 'text', 'content' => $messagePaso1, 'wa_delay' => 5],
            ];

            $templatePath = $this->resolveTemplateFromS3('excel-confirmacion/EXCEL_DE_CONFIRMACION_GENERAL.xlsx');
            if ($templatePath === null) {
                Log::error('SolicitarDocumentosWhatsAppJob: plantilla Excel de confirmación no encontrada en almacenamiento');

                return;
            }
            $outputDir = storage_path('app/temp/excel-confirmacion');
            if (!is_dir($outputDir)) {
                @mkdir($outputDir, 0775, true);
            }

            $excelLinks = [];

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

                $steps[] = [
                    'type' => 'file',
                    'path' => $fullPath,
                    'filename' => $fileName,
                    'caption' => 'Documento de confirmación' . ($codeSupplier !== '' ? ' - ' . $codeSupplier : ''),
                    'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'wa_delay' => 5,
                    'code_supplier' => $codeSupplier,
                ];

                $link = $driveExcelService->uploadForProveedor(
                    $cargaCode,
                    $nombreCliente !== '' ? $nombreCliente : 'Cliente',
                    $codeSupplier !== '' ? $codeSupplier : $suffixArchivo,
                    $fullPath,
                    $fileName
                );

                if ($link !== null && $cotizacionProveedor) {
                    $cotizacionProveedor->excel_confirmacion_drive_link = $link;
                    $cotizacionProveedor->save();

                    Log::info('SolicitarDocumentosWhatsAppJob: Excel subido a Drive', [
                        'id_proveedor' => $cotizacionProveedor->id,
                        'code_supplier' => $codeSupplier,
                        'drive_link' => $link,
                    ]);
                } elseif (!$driveExcelService->isConfigured()) {
                    Log::warning('SolicitarDocumentosWhatsAppJob: Google Drive no configurado para Excel de confirmación', [
                        'id_proveedor' => $prov['id'] ?? null,
                        'file' => $fileName,
                    ]);
                } else {
                    Log::warning('SolicitarDocumentosWhatsAppJob: fallo subida Excel a Drive', [
                        'id_proveedor' => $prov['id'] ?? null,
                        'file' => $fileName,
                    ]);
                }

                if ($link !== null && $cotizacionProveedor) {
                    $excelLinks[] = [
                        'id_proveedor' => (int) $cotizacionProveedor->id,
                        'code_supplier' => $codeSupplier,
                    ];
                }
            }

            $steps[] = ['type' => 'text', 'content' => $messagePaso2, 'wa_delay' => 8];
            if ($considerationsFile !== null) {
                $steps[] = [
                    'type' => 'file',
                    'path' => $considerationsFile['email_path'],
                    'filename' => $considerationsFile['filename'],
                    'caption' => 'Consideraciones',
                    'mime' => $considerationsFile['mime'],
                    'wa_delay' => 10,
                ];
            }

            $useMeta = $this->shouldRouteCoordinacionToMeta('consolidado');

            if ($useMeta) {
                $this->dispatchMetaDocumentosBatch(
                    $telefono,
                    $cargaCode,
                    $messagePaso1,
                    $messagePaso2,
                    $excelLinks,
                    $considerationsFile,
                    $nombreCliente,
                    $fecha_documentacion_max_formatted
                );
            } else {
                foreach ($steps as $step) {
                    $delay = (int) ($step['wa_delay'] ?? 5);

                    if (($step['type'] ?? '') === 'text') {
                        $response = $this->sendMessage($step['content'] ?? '', $telefono, $delay);
                        Log::info('SolicitarDocumentosWhatsAppJob texto WhatsApp: ' . json_encode($response));
                        continue;
                    }

                    $path = $step['path'] ?? '';
                    if ($path === '' || !is_file($path)) {
                        Log::warning('SolicitarDocumentosWhatsAppJob: archivo no encontrado para WhatsApp', ['path' => $path]);
                        continue;
                    }

                    $response = $this->sendMedia(
                        $path,
                        $step['mime'] ?? 'application/octet-stream',
                        $step['caption'] ?? 'Archivo',
                        $telefono,
                        $delay
                    );
                    Log::info('SolicitarDocumentosWhatsAppJob archivo WhatsApp: ' . json_encode($response));
                }
            }

            $clienteNombre = $nombreCliente !== '' ? $nombreCliente : 'Cliente';

            SolicitarDocumentosEmailJob::dispatch(
                $this->idCotizacion,
                $clienteNombre,
                $cargaCode,
                $steps
            );
        } catch (\Throwable $e) {
            Log::error('SolicitarDocumentosWhatsAppJob error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * @param  array<int, array{id_proveedor: int, code_supplier: string}>  $excelLinks
     * @param  array{meta_url: string, email_path: string, filename: string, mime: string}|null  $considerationsFile
     */
    private function dispatchMetaDocumentosBatch(
        string $telefono,
        string $cargaCode,
        string $messagePaso1,
        string $messagePaso2,
        array $excelLinks,
        ?array $considerationsFile,
        string $nombreCliente,
        ?string $fechaDocumentacionMax
    ): void {
        $phoneE164 = preg_replace('/[^0-9]/', '', str_replace('@c.us', '', $telefono));
        $batch = app(WhatsAppCoordinacionBatchService::class)->create('solicitar_documentos', [
            'id_cotizacion' => $this->idCotizacion,
            'cliente' => $nombreCliente !== '' ? $nombreCliente : 'Cliente',
            'carga' => $cargaCode,
            'phone_e164' => $phoneE164,
            'job_domain' => $this->domain,
        ]);
        $this->setWhatsAppCoordinacionBatchId((int) $batch->id);

        $this->queueCoordinacionWhatsApp(
            CoordinacionWhatsappPayload::docsPaso1($telefono, $cargaCode, $messagePaso1, 5),
            'docs_paso1',
            'Paso 1 — Excel y video'
        );

        foreach ($excelLinks as $excel) {
            $idProveedor = (int) ($excel['id_proveedor'] ?? 0);
            if ($idProveedor <= 0) {
                continue;
            }
            $code = (string) ($excel['code_supplier'] ?? '');
            $this->queueCoordinacionWhatsApp(
                CoordinacionWhatsappPayload::docsExcelLinkForProveedor(
                    $telefono,
                    $cargaCode,
                    $idProveedor,
                    $code,
                    5
                ),
                'docs_excel_' . $idProveedor,
                'Excel Drive — ' . ($code !== '' ? $code : (string) $idProveedor)
            );
        }

        $fechaParam = ($this->validateMaxDate && $fechaDocumentacionMax) ? $fechaDocumentacionMax : null;
        $this->queueCoordinacionWhatsApp(
            CoordinacionWhatsappPayload::docsPaso2($telefono, $messagePaso2, $fechaParam, 8),
            'docs_paso2',
            'Paso 2 — Word guía'
        );

        if ($considerationsFile !== null) {
            $this->queueCoordinacionWhatsApp(
                CoordinacionWhatsappPayload::docsConsideraciones(
                    $telefono,
                    $considerationsFile['meta_url'],
                    'Consideraciones para la documentación de tu importación. 📋',
                    $considerationsFile['filename'],
                    $considerationsFile['mime'],
                    10
                ),
                'docs_consideraciones',
                'Consideraciones (D04)'
            );
        }

        Log::info('SolicitarDocumentosWhatsAppJob: batch Meta despachado', [
            'batch_id' => $this->getWhatsAppCoordinacionBatchId(),
            'laravel_batch_id' => $this->dispatchWhatsAppCoordinacionBatch(),
            'id_cotizacion' => $this->idCotizacion,
        ]);
    }

    /**
     * @return array{meta_url: string, email_path: string, filename: string, mime: string}|null
     */
    private function resolveConsiderationsFile(): ?array
    {
        $storage = $this->objectStorage();
        if (!$storage instanceof S3ObjectStorageConnector || config('object_storage.upload_disk') !== 's3') {
            return null;
        }

        $candidates = [
            ['file' => 'CONSIDERATIONS.pdf', 'filename' => 'CONSIDERATIONS.pdf', 'mime' => 'application/pdf'],
            ['file' => 'CONSIDERATIONS.docx', 'filename' => 'CONSIDERATIONS.docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ];

        foreach ($candidates as $candidate) {
            $relative = self::S3_TEMPLATES_BASE . '/' . $candidate['file'];
            if (!$storage->existsOnS3($relative)) {
                continue;
            }

            try {
                $metaUrl = $storage->metaPresignedUrl($relative);
            } catch (\Throwable $e) {
                continue;
            }

            if (!filter_var($metaUrl, FILTER_VALIDATE_URL)) {
                continue;
            }

            $emailPath = $this->resolveReadableFileFromS3($relative);
            if ($emailPath === null) {
                continue;
            }

            return [
                'meta_url' => $metaUrl,
                'email_path' => $emailPath,
                'filename' => $candidate['filename'],
                'mime' => $candidate['mime'],
            ];
        }

        Log::warning('SolicitarDocumentosWhatsAppJob: CONSIDERATIONS no encontrado en S3', [
            'prefix' => self::S3_TEMPLATES_BASE,
        ]);

        return null;
    }

    private function resolveTemplateFromS3(string $fileUnderTemplates): ?string
    {
        $relative = self::S3_TEMPLATES_BASE . '/' . ltrim($fileUnderTemplates, '/');
        $resolved = $this->resolveReadableFileFromS3($relative);
        if ($resolved === null) {
            Log::warning('SolicitarDocumentosWhatsAppJob: plantilla no encontrada en S3', ['file' => $fileUnderTemplates]);
        }

        return $resolved;
    }

    private function resolveReadableFileFromS3(string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $storage = $this->objectStorage();
        if (!$storage instanceof S3ObjectStorageConnector || config('object_storage.upload_disk') !== 's3') {
            return null;
        }

        try {
            if (!$storage->existsOnS3($relative)) {
                return null;
            }

            $path = $storage->localPath($relative);

            return is_file($path) ? $path : null;
        } catch (\Throwable $e) {
            Log::warning('SolicitarDocumentosWhatsAppJob: fallo lectura S3', [
                'relative' => $relative,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
