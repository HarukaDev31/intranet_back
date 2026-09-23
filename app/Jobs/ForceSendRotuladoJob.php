<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Services\CargaConsolidada\MovilidadPersonalVimService;
use App\Services\Organizacion\OrganizacionMensajeriaService;
use App\Services\WhatsApp\WhatsAppCoordinacionBatchService;
use App\Support\WhatsApp\CoordinacionMediaLink;
use App\Support\WhatsApp\CoordinacionWhatsappPayload;
use App\Traits\WhatsappTrait;
use App\Traits\DatabaseConnectionTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;
use ZipArchive;
use Dompdf\Dompdf;
use Dompdf\Options;

class ForceSendRotuladoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait, DatabaseConnectionTrait;

    protected $idCotizacion;
    protected $proveedoresIds;
    protected $idContainer;
    protected $domain;

    /**
     * Create a new job instance.
     */
    public function __construct($idCotizacion, $proveedoresIds, $idContainer, $domain = null)
    {
        $this->idCotizacion = $idCotizacion;
        $this->proveedoresIds = $proveedoresIds;
        $this->idContainer = $idContainer;
        $this->domain = $domain;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            // Establecer la conexión de BD basándose en el dominio
            $this->setDatabaseConnection($this->domain);
            $this->setWhatsappFlujo('rotulado');

            Log::info("Iniciando ForceSendRotuladoJob", [
                'id_cotizacion' => $this->idCotizacion,
                'proveedores_ids' => $this->proveedoresIds,
                'id_container' => $this->idContainer,
                'domain' => $this->domain
            ]);

            DB::beginTransaction();

            $cotizacionInfo = Cotizacion::where('id', $this->idCotizacion)->first();
            if (!$cotizacionInfo) {
                throw new Exception("Cotización no encontrada");
            }

            $orgId = (int) $cotizacionInfo->getAttribute('organizacion_id');
            if ($orgId <= 0) {
                $orgId = OrganizacionMensajeriaService::ID_ORGANIZACION_ADMIN;
            }
            $this->setWhatsappOrganizacionId($orgId);
            $mensajeria = app(OrganizacionMensajeriaService::class);
            if (!$mensajeria->rotuladoHabilitado($orgId)) {
                Log::info('ForceSendRotuladoJob omitido: rotulado deshabilitado', [
                    'organizacion_id' => $orgId,
                    'id_cotizacion' => $this->idCotizacion,
                ]);
                DB::rollBack();
                return;
            }

            $telefono = preg_replace('/\s+/', '', $cotizacionInfo->telefono);
            $this->phoneNumberId = $telefono ? $telefono . '@c.us' : '';

            // Obtener todos los proveedores para esta cotización
            $totalproveedores = CotizacionProveedor::where('id_cotizacion', $this->idCotizacion)->get()->toArray();
            $contenedor = Contenedor::where('id', $this->idContainer)->first();
            if (!$contenedor) {
                throw new Exception("Contenedor no encontrado");
            }
            $carga = $contenedor->carga;

            if (
                $this->whatsappCoordinacionBatchId === null
                && $this->shouldRouteCoordinacionToMeta('consolidado')
                && $this->phoneNumberId
            ) {
                $phoneE164 = preg_replace('/[^0-9]/', '', (string) $this->phoneNumberId);
                $batch = app(WhatsAppCoordinacionBatchService::class)->create('rotulado', [
                    'id_cotizacion' => $this->idCotizacion,
                    'cliente' => $cotizacionInfo->nombre,
                    'carga' => (string) $carga,
                    'phone_e164' => $phoneE164,
                    'job_domain' => $this->domain,
                ]);
                $this->whatsappCoordinacionBatchId = (int) $batch->id;
            }
            if ($this->whatsappCoordinacionBatchId !== null) {
                $this->setWhatsAppCoordinacionBatchId($this->whatsappCoordinacionBatchId);
            }

            // Procesar plantilla de bienvenida
            $htmlWelcomePath = public_path('assets/templates/Welcome_Consolidado_Template.html');
            if (!file_exists($htmlWelcomePath)) {
                throw new Exception("No se encontró la plantilla de bienvenida");
            }

            $htmlWelcomeContent = file_get_contents($htmlWelcomePath);
            $htmlWelcomeContent = mb_convert_encoding($htmlWelcomeContent, 'UTF-8', mb_detect_encoding($htmlWelcomeContent));
            $htmlWelcomeContent = str_replace('{{consolidadoNumber}}', $carga, $htmlWelcomeContent);

            // Determinar si enviar mensaje de bienvenida
            $sendWelcome = (count($this->proveedoresIds) == count($totalproveedores));

            // Enviar mensaje de bienvenida si es necesario
            if ($sendWelcome) {
                $this->sendWelcome($carga);
                Log::info('Mensaje de bienvenida enviado - procesando todos los proveedores');
            } else {
                $nuevoMsg = "Hola 🙋🏻‍♀, te escribe el área de coordinación de Probusiness. 

📢 Añadiste un nuevo proveedor en el *Consolidado #${carga}*

*Rotulado: 👇🏼*  
Tienes que indicarle a tu proveedor que las cajas máster 📦 cuenten con un rotulado para 
identificar tus paquetes y diferenciarlas de los demás cuando llegue a nuestro almacén.";
                $this->sendMessage(
                    $nuevoMsg,
                    $this->phoneNumberId,
                    0,
                    'consolidado',
                    CoordinacionWhatsappPayload::rotuladoNuevoProveedor((string) $this->phoneNumberId, (string) $carga, $nuevoMsg)
                );
                Log::info('Mensaje de nuevo proveedor enviado - procesando proveedores específicos');
            }

            $sleepSendMedia = 7;

            // Configurar ZIP
            $zipFileName = storage_path('app/Rotulado.zip');
            $zipDirectory = dirname($zipFileName);

            Log::info('Configurando ZIP: ' . $zipFileName);

            // Asegurar que el directorio existe
            if (!is_dir($zipDirectory)) {
                mkdir($zipDirectory, 0755, true);
                Log::info('Directorio creado: ' . $zipDirectory);
            }

            // Eliminar archivo ZIP existente si existe
            if (file_exists($zipFileName)) {
                unlink($zipFileName);
                Log::info('ZIP anterior eliminado');
            }

            $zip = new ZipArchive();
            $zipResult = $zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($zipResult !== TRUE) {
                Log::error('No se pudo crear el archivo ZIP. Código de error: ' . $zipResult);
                throw new Exception("No se pudo crear el archivo ZIP. Código: $zipResult");
            }

            Log::info('ZIP creado correctamente');

            // Configuración de DomPDF
            $options = new Options();
            $options->set('isHtml5ParserEnabled', false);
            $options->set('isFontSubsettingEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('isPhpEnabled', true);
            $options->set('chroot', public_path());
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('defaultMediaType', 'screen');
            $options->set('isFontSubsettingEnabled', false);
            $options->set('debugKeepTemp', false);
            $options->set('debugCss', false);
            $options->set('debugLayout', false);
            $options->set('debugLayoutLines', false);
            $options->set('debugLayoutBlocks', false);
            $options->set('debugLayoutInline', false);
            $options->set('debugLayoutPaddingBox', false);

            $processedProviders = 0;

            // Obtener información del cliente
            $cotizacionCliente = Cotizacion::where('id', $this->idCotizacion)->first();
            $cliente = $cotizacionCliente ? $cotizacionCliente->nombre : '';

            // Filtrar proveedores que están en la lista de IDs proporcionados
            $proveedoresFiltrados = array_filter($totalproveedores, function ($proveedor) {
                $proveedorId = is_array($proveedor) ? $proveedor['id'] : $proveedor->id;
                return in_array($proveedorId, $this->proveedoresIds);
            });

            Log::info('Proveedores a procesar: ', [
                'total_disponibles' => count($totalproveedores),
                'total_filtrados' => count($proveedoresFiltrados),
                'ids_solicitados' => $this->proveedoresIds
            ]);

            if (empty($proveedoresFiltrados)) {
                throw new Exception("No se encontraron proveedores válidos para procesar");
            }

            // Procesar cada proveedor filtrado
            foreach ($proveedoresFiltrados as $proveedor) {
                // Asegurar que trabajamos con un array
                $proveedorArray = is_array($proveedor) ? $proveedor : (array) $proveedor;

                Log::info('Procesando proveedor: ' . json_encode($proveedorArray));
                $supplierCode = $proveedorArray['code_supplier'] ?? '';
                $products = $proveedorArray['products'] ?? '';
                $sleepSendMedia += 1;

                $pdfService = app(\App\Services\CargaConsolidada\RotuladoPdfService::class);
                $htmlContent = $pdfService->buildHtmlForCotizacion($cliente, $supplierCode, $carga, $cotizacionInfo);

                Log::info('HTML procesado para proveedor: ' . $supplierCode);

                // Generar PDF
                try {
                    Log::info('Iniciando generación de PDF para proveedor: ' . $supplierCode);

                    $pdfContent = $pdfService->renderPdf($htmlContent);

                    Log::info('PDF generado exitosamente');
                } catch (Exception $pdfException) {
                    Log::error('Error generando PDF: ' . $pdfException->getMessage());
                    Log::error('Stack trace: ' . $pdfException->getTraceAsString());
                    throw new Exception('Error generando PDF para proveedor ' . $supplierCode . ': ' . $pdfException->getMessage());
                }

                Log::info('PDF generado para proveedor: ' . $supplierCode . ', tamaño: ' . strlen($pdfContent));

                // Guardar temporalmente
                $tempFilePath = storage_path("app/temp_document_proveedor{$supplierCode}.pdf");
                if (file_exists($tempFilePath)) {
                    unlink($tempFilePath);
                }

                if (file_put_contents($tempFilePath, $pdfContent) === false) {
                    Log::error('No se pudo guardar PDF temporal: ' . $tempFilePath);
                    throw new Exception("No se pudo guardar el PDF temporal");
                }

                Log::info('PDF guardado temporalmente: ' . $tempFilePath);

                try {
                    if (!$zip->addFile($tempFilePath, "Rotulado_{$supplierCode}.pdf")) {
                        Log::error("No se pudo añadir $tempFilePath al ZIP");
                        continue;
                    }

                    Log::info("Archivo añadido al ZIP: Rotulado_{$supplierCode}.pdf");

                    // Enviar documento al proveedor
                    $rotCaption = "Producto: {$products}\nCódigo de proveedor: {$supplierCode}";
                    $this->sendDataItem(
                        $rotCaption,
                        $tempFilePath,
                        $this->phoneNumberId,
                        $sleepSendMedia,
                        "Rotulado_{$supplierCode}.pdf",
                        CoordinacionWhatsappPayload::rotuladoPdfProducto(
                            (string) $this->phoneNumberId,
                            (string) $products,
                            (string) $supplierCode,
                            $tempFilePath,
                            $rotCaption,
                            $sleepSendMedia
                        )
                    );

                    $tipoRotulado = strtolower(trim((string) ($proveedorArray['tipo_rotulado'] ?? '')));
                    if ($tipoRotulado === 'movilidad_personal') {
                        $sleepSendMedia += 1;
                        $sleepSendMedia = $this->enviarRotuladoMovilidadPersonal(
                            $proveedorArray,
                            $cotizacionInfo,
                            $carga,
                            $sleepSendMedia
                        );
                    }

                    $processedProviders++;
                    if (!empty($proveedorArray['id'])) {
                        CotizacionProveedor::where('id', $proveedorArray['id'])->update([
                            'send_rotulado_status' => 'SENDED',
                        ]);
                    }
                } catch (Exception $e) {
                    Log::error('Error procesando proveedor ' . $supplierCode . ': ' . $e->getMessage());
                    continue;
                } finally {
                    // Limpiar memoria
                    gc_collect_cycles();
                }
            }

            Log::info("Total de proveedores procesados: $processedProviders");

            // Cerrar ZIP
            if (!$zip->close()) {
                Log::error("Error al cerrar el archivo ZIP");
                throw new Exception("Error al cerrar el archivo ZIP");
            }

            Log::info('ZIP cerrado correctamente');

            // Enviar imagen de dirección (socio sin foto → org 1 / jpeg por defecto)
            $direccionUrl = $mensajeria->localPathImagenConFallback($orgId, OrganizacionMensajeriaService::IMG_DIRECCION);
            if ($direccionUrl && is_file($direccionUrl)) {
                $sleepSendMedia += 3;
                $dirCaption = 'Dile a tu proveedor que envíe la carga a nuestro almacén en China';
                $this->sendMedia(
                    $direccionUrl,
                    'image/jpeg',
                    $dirCaption,
                    $this->phoneNumberId,
                    $sleepSendMedia,
                    'consolidado',
                    'Direccion_almacen_China.jpeg',
                    CoordinacionWhatsappPayload::rotuladoAlmacenChinaImg((string) $this->phoneNumberId, $direccionUrl, $dirCaption, $sleepSendMedia)
                );
            }

            // Ya no se envía pb_rotulado_datos_proveedor_v1 al pedir rotulado.

            // Verificar que el ZIP se generó correctamente
            if (!file_exists($zipFileName)) {
                Log::error("El archivo ZIP no existe después de cerrarlo: $zipFileName");
                throw new Exception("El archivo ZIP no se generó correctamente");
            }

            $fileSize = filesize($zipFileName);
            Log::info("Tamaño del ZIP generado: $fileSize bytes");

            if ($fileSize === false || $fileSize == 0) {
                Log::error("El archivo ZIP está vacío o no se puede leer");
                throw new Exception("El archivo ZIP está vacío");
            }

            if ($this->whatsappCoordinacionBatchId !== null) {
                $laravelBatchId = $this->dispatchWhatsAppCoordinacionBatch();
                Log::info('ForceSendRotuladoJob: batch WhatsApp despachado', [
                    'batch_id' => $this->whatsappCoordinacionBatchId,
                    'laravel_batch_id' => $laravelBatchId,
                ]);
            }

            DB::commit();

            Log::info("ForceSendRotuladoJob completado exitosamente", [
                'id_cotizacion' => $this->idCotizacion,
                'proveedores_procesados' => $processedProviders,
                'zip_file' => $zipFileName
            ]);

            // Limpiar archivos temporales después de un breve delay
            register_shutdown_function(function () use ($zipFileName) {
                if (file_exists($zipFileName)) {
                    sleep(2); // Esperar 2 segundos antes de eliminar
                    unlink($zipFileName);
                    Log::info("Archivo ZIP temporal eliminado: $zipFileName");
                }
            });

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error en ForceSendRotuladoJob: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Genera códigos VIN/VIM, escribe el sheet y envía PDF + Excel por WhatsApp.
     *
     * @param array<string, mixed> $proveedorArray
     */
    private function enviarRotuladoMovilidadPersonal($proveedorArray, $cotizacionInfo, $carga, $sleepSendMedia): int
    {
        $supplierCode = (string) ($proveedorArray['code_supplier'] ?? '');
        $vimService = app(MovilidadPersonalVimService::class);
        $result = $vimService->generateForProveedor($cotizacionInfo, $proveedorArray, $carga);

        if ($result === null) {
            Log::error('ForceSendRotuladoJob: no se generaron códigos VIM para movilidad personal', [
                'id_cotizacion' => $this->idCotizacion,
                'code_supplier' => $supplierCode,
            ]);

            return (int) $sleepSendMedia;
        }

        $message = "👆🏻 ⚠ Atención ⚠
Etiqueta especial: Movilidad Personal

Según la regulación de Aduanas - Perú todos los Scooters / Monociclos /Bicimotos / Trimotos requiere tener código VIN y Motor grabado en el producto de manera obligatoria.

Por lo tanto, dile a tu proveedor #{$supplierCode} que le ponga la etiqueta.

⛔ No aceptamos cargas sin código VIN o Motor ya que la aduana lo puede observar o decomisar.
📝 Aquí tienes el archivo con los códigos generados";

        $movilidadPersonalPath = $vimService->ejemploPdfPath();
        if ($movilidadPersonalPath) {
            $this->sendMedia(
                $movilidadPersonalPath,
                'application/pdf',
                $message,
                $this->phoneNumberId,
                $sleepSendMedia,
                'consolidado',
                'movilidad_personal_ejemplo.pdf',
                CoordinacionWhatsappPayload::rotuladoPdfProducto(
                    (string) $this->phoneNumberId,
                    'Movilidad Personal',
                    $supplierCode,
                    $movilidadPersonalPath,
                    $message,
                    (int) $sleepSendMedia
                )
            );
            Log::info('ForceSendRotuladoJob: PDF movilidad personal enviado', [
                'code_supplier' => $supplierCode,
            ]);
        } else {
            Log::warning('ForceSendRotuladoJob: no se encontró PDF de ejemplo movilidad_personal');
        }

        $excelPath = $result['excel_path'];
        if (is_file($excelPath)) {
            $sleepSendMedia += 1;
            $vinMessage = "👆🏼 Le adjuntamos la lista de códigos VIN que deben ir grabados en los vehículos de movilidad personal.\n\nDescárgala aquí: {{link}} 📋";
            $linkVin = CoordinacionMediaLink::uploadLocalFile($excelPath, 'temp/vin/' . basename($excelPath));
            if ($linkVin !== null) {
                $vinMessage = str_replace('{{link}}', $linkVin, $vinMessage);
            }
            $metaVin = $linkVin !== null
                ? CoordinacionWhatsappPayload::rotuladoVinLink(
                    (string) $this->phoneNumberId,
                    $linkVin,
                    $vinMessage,
                    (int) $sleepSendMedia
                )
                : null;
            $this->sendMessage($vinMessage, $this->phoneNumberId, $sleepSendMedia, 'consolidado', $metaVin);
            Log::info('ForceSendRotuladoJob: enlace VIN enviado', [
                'code_supplier' => $supplierCode,
                'codes_count' => count($result['codes']),
            ]);
        }

        return (int) $sleepSendMedia;
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception)
    {
        Log::error('ForceSendRotuladoJob falló', [
            'id_cotizacion' => $this->idCotizacion,
            'proveedores_ids' => $this->proveedoresIds,
            'id_container' => $this->idContainer,
            'error' => $exception->getMessage()
        ]);
    }
}
