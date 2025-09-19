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
use App\Traits\WhatsappTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;
use ZipArchive;
use Dompdf\Dompdf;
use Dompdf\Options;

class ForceSendRotuladoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait;

    protected $idCotizacion;
    protected $proveedoresIds;
    protected $idContainer;

    /**
     * Create a new job instance.
     */
    public function __construct($idCotizacion, $proveedoresIds, $idContainer)
    {
        $this->idCotizacion = $idCotizacion;
        $this->proveedoresIds = $proveedoresIds;
        $this->idContainer = $idContainer;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            Log::info("Iniciando ForceSendRotuladoJob", [
                'id_cotizacion' => $this->idCotizacion,
                'proveedores_ids' => $this->proveedoresIds,
                'id_container' => $this->idContainer
            ]);

            DB::beginTransaction();

            $cotizacionInfo = Cotizacion::where('id', $this->idCotizacion)->first();
            if (!$cotizacionInfo) {
                throw new Exception("Cotización no encontrada");
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
                $this->sendMessage("Hola 🙋🏻‍♀, te escribe el área de coordinación de Probusiness. 

📢 Añadiste un nuevo proveedor en el *Consolidado #${carga}*

*Rotulado: 👇🏼*  
Tienes que indicarle a tu proveedor que las cajas máster 📦 cuenten con un rotulado para 
identificar tus paquetes y diferenciarlas de los demás cuando llegue a nuestro almacén.");
                Log::info('Mensaje de nuevo proveedor enviado - procesando proveedores específicos');
            }

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
            $sleepSendMedia = 7;

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

                // Procesar plantilla de rotulado
                $htmlFilePath = public_path('assets/templates/Rotulado_Template.html');
                if (!file_exists($htmlFilePath)) {
                    Log::error('No se encontró plantilla de rotulado: ' . $htmlFilePath);
                    throw new Exception("No se encontró la plantilla de rotulado");
                }

                $htmlContent = file_get_contents($htmlFilePath);
                $htmlContent = mb_convert_encoding($htmlContent, 'UTF-8', mb_detect_encoding($htmlContent));

                // Convertir imagen a base64
                $headerImagePath = public_path('assets/templates/ROTULADO_HEADER.png');
                $headerImageBase64 = '';
                if (file_exists($headerImagePath)) {
                    $imageData = file_get_contents($headerImagePath);
                    $headerImageBase64 = 'data:image/png;base64,' . base64_encode($imageData);
                    Log::info('Imagen convertida a base64 exitosamente');
                } else {
                    Log::error('No se encontró la imagen header: ' . $headerImagePath);
                }

                $htmlContent = str_replace('{{cliente}}', $cliente, $htmlContent);
                $htmlContent = str_replace('{{supplier_code}}', $supplierCode, $htmlContent);
                $htmlContent = str_replace('{{carga}}', $carga, $htmlContent);
                $htmlContent = str_replace('{{base_url}}/assets/templates/ROTULADO_HEADER.png', $headerImageBase64, $htmlContent);

                Log::info('HTML procesado para proveedor: ' . $supplierCode);

                // Generar PDF
                try {
                    Log::info('Iniciando generación de PDF para proveedor: ' . $supplierCode);

                    $dompdf = new Dompdf($options);
                    $dompdf->loadHtml($htmlContent);
                    $dompdf->setPaper('A4', 'portrait');

                    Log::info('PDF configurado, iniciando render...');
                    $dompdf->render();

                    Log::info('Render completado, obteniendo output...');
                    $pdfContent = $dompdf->output();

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
                    $this->sendDataItem(
                        "Producto: {$products}\nCódigo de proveedor: {$supplierCode}",
                        $tempFilePath
                    );

                    $processedProviders++;
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

            // Enviar imagen de dirección
            $direccionUrl = public_path('assets/images/Direccion.jpg');
            $sleepSendMedia += 3;
            $this->sendMedia($direccionUrl, 'image/jpg', '🏽Dile a tu proveedor que envíe la carga a nuestro almacén en China', null, $sleepSendMedia);

            // Enviar mensaje adicional
            $sleepSendMedia += 3;
            $this->sendMessage("También necesito los datos de tu proveedor para comunicarnos y recibir tu carga.

➡ *Datos del proveedor: (Usted lo llena)*

☑ Nombre del producto:
☑ Nombre del vendedor:
☑ Celular del vendedor:

Te avisaré apenas tu carga llegue a nuestro almacén de China, cualquier duda me escribes. 🫡", null, $sleepSendMedia);

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
