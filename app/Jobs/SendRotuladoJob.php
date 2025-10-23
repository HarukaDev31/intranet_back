<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Traits\WhatsappTrait;

class SendRotuladoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait;

    protected $cliente;
    protected $carga;
    protected $proveedores;
    protected $idCotizacion;

    /**
     * Create a new job instance.
     */
    public function __construct($cliente, $carga, $proveedores, $idCotizacion)
    {
        $this->cliente = $cliente;
        $this->carga = $carga;
        $this->proveedores = $proveedores;
        $this->idCotizacion = $idCotizacion;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Iniciando SendRotuladoJob', [
                'cliente' => $this->cliente,
                'carga' => $this->carga,
                'proveedores_count' => count($this->proveedores),
                'id_cotizacion' => $this->idCotizacion
            ]);

            // Obtener información de la cotización para configurar el teléfono
            $cotizacionInfo = Cotizacion::where('id', $this->idCotizacion)->first();
            if ($cotizacionInfo) {
                $telefono = preg_replace('/\s+/', '', $cotizacionInfo->telefono);
                $this->phoneNumberId = $telefono ? $telefono . '@c.us' : '';
                Log::info('Teléfono configurado: ' . $this->phoneNumberId);
            } else {
                Log::warning('No se encontró la cotización con ID: ' . $this->idCotizacion);
            }

            // Asegurar que $proveedores sea un array
            $proveedores = collect($this->proveedores)->map(function ($proveedor) {
                return is_object($proveedor) ? (array) $proveedor : $proveedor;
            })->toArray();

            // Debug: Ver la estructura de los proveedores
            Log::info('Estructura del primer proveedor: ' . json_encode($proveedores[0] ?? 'No hay proveedores'));

            // Obtener estados de proveedores desde la base de datos
            $proveedorIds = collect($proveedores)->filter(function ($proveedor) {
                return isset($proveedor['id']) && !empty($proveedor['id']);
            })->pluck('id')->toArray();
            
            $proveedoresFromDB = collect();
            if (!empty($proveedorIds)) {
                $proveedoresFromDB = CotizacionProveedor::whereIn('id', $proveedorIds)
                    ->get()
                    ->keyBy('id');
            }

            // Filtrar proveedores según estado desde BD
            $providersHasSended = [];
            $providersHasNoSended = [];
            Log::info('Proveedores from DB: ' . json_encode($proveedoresFromDB));
            Log::info('Proveedores: ' . json_encode($proveedores));
            foreach ($proveedores as $proveedor) {
                // Verificar que el proveedor tenga id
                if (!isset($proveedor['id']) || empty($proveedor['id'])) {
                    Log::warning('Proveedor sin ID válido: ' . json_encode($proveedor));
                    $providersHasNoSended[] = $proveedor;
                    continue;
                }
                
                $proveedorDB = $proveedoresFromDB->get($proveedor['id']);
                Log::info('Proveedor DB: ' . json_encode($proveedorDB));
                if ($proveedorDB && $proveedorDB->send_rotulado_status === 'SENDED') {
                    $providersHasSended[] = $proveedor;
                } else {
                    $providersHasNoSended[] = $proveedor;
                }
            }

            if (empty($providersHasNoSended)) {
                Log::warning('No hay proveedores pendientes de envío');
                return;
            }
            Log::info('Providers has sended: ' . json_encode($providersHasSended));
            // Enviar mensaje de bienvenida si es necesario
            if (count($providersHasSended) == 0) {
                Log::info('Enviando mensaje de bienvenida - no hay proveedores enviados previamente');
                $result = $this->sendWelcome($this->carga);
                Log::info('Resultado del envío de bienvenida: ' . json_encode($result));
            } elseif (count($providersHasSended) > 0 && count($providersHasNoSended) > 0) {
                $this->sendMessage("Hola 🙋🏻‍♀, te escribe el área de coordinación de Probusiness. 
        
📢 Añadiste un nuevo proveedor en el *Consolidado #{$this->carga}*

*Rotulado: 👇🏼*  
Tienes que indicarle a tu proveedor que las cajas máster 📦 cuenten con un rotulado para 
identificar tus paquetes y diferenciarlas de los demás cuando llegue a nuestro almacén.");
            }

            // Configurar ZIP
            $zipFileName = storage_path('app/Rotulado.zip');
            $zipDirectory = dirname($zipFileName);

            if (!is_dir($zipDirectory)) {
                mkdir($zipDirectory, 0755, true);
            }

            if (file_exists($zipFileName)) {
                unlink($zipFileName);
            }

            $zip = new ZipArchive();
            $zipResult = $zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($zipResult !== TRUE) {
                throw new \Exception("No se pudo crear el archivo ZIP. Código: $zipResult");
            }

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

            // Procesar cada proveedor pendiente
            foreach ($providersHasNoSended as $proveedor) {
                $proveedorDB = $proveedoresFromDB->get($proveedor['id']);
                if (!$proveedorDB) {
                    Log::warning('Proveedor no encontrado en BD: ' . $proveedor['id']);
                    continue;
                }

                $supplierCode = $proveedorDB->code_supplier ?? '';
                $products = $proveedorDB->products ?? '';
                $tipoRotulado = $proveedor['tipo_rotulado'] ?? 'rotulado';
                $sleepSendMedia += 1;

                Log::info('Procesando proveedor', [
                    'id' => $proveedor['id'],
                    'supplier_code' => $supplierCode,
                    'products' => $products,
                    'tipo_rotulado' => $tipoRotulado
                ]);

                // Generar rotulado según el tipo
                $pdfContent = $this->generateRotuladoByType($tipoRotulado, $supplierCode, $products);

                // Guardar temporalmente
                $tempFilePath = storage_path("app/temp_document_proveedor{$supplierCode}.pdf");
                if (file_exists($tempFilePath)) {
                    unlink($tempFilePath);
                }

                if (file_put_contents($tempFilePath, $pdfContent) === false) {
                    Log::error('No se pudo guardar PDF temporal: ' . $tempFilePath);
                    continue;
                }

                try {
                    if (!$zip->addFile($tempFilePath, "Rotulado_{$supplierCode}.pdf")) {
                        Log::error("No se pudo añadir $tempFilePath al ZIP");
                        continue;
                    }

                    // Enviar documento principal al proveedor
                    $this->sendDataItem(
                        "Producto: {$products}\nCódigo de proveedor: {$supplierCode}",
                        $tempFilePath
                    );

                    // Enviar mensaje e imagen específicos por tipo
                    $this->sendRotuladoByType($tipoRotulado, $supplierCode, $products, $sleepSendMedia);

                    // Actualizar estado del proveedor
                    $proveedorDB->update(["send_rotulado_status" => "SENDED"]);

                    $processedProviders++;
                } catch (\Exception $e) {
                    Log::error('Error procesando proveedor ' . $supplierCode . ': ' . $e->getMessage());
                    continue;
                } finally {
                    gc_collect_cycles();
                }
            }

            Log::info("Total de proveedores procesados: $processedProviders");

            // Cerrar ZIP
            if (!$zip->close()) {
                throw new \Exception("Error al cerrar el archivo ZIP");
            }

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

            Log::info('SendRotuladoJob completado exitosamente');
        } catch (\Exception $e) {
            Log::error('Error en SendRotuladoJob: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Generar rotulado según el tipo
     */
    private function generateRotuladoByType($tipoRotulado, $supplierCode, $products)
    {
        switch ($tipoRotulado) {
            case 'rotulado':
                return $this->generateRotuladoGeneral($supplierCode, $products);
            case 'calzado':
                return $this->generateRotuladoCalzado($supplierCode, $products);
            case 'ropa':
                return $this->generateRotuladoRopa($supplierCode, $products);
            case 'ropa_interior':
                return $this->generateRotuladoRopaInterior($supplierCode, $products);
            case 'maquinaria':
                return $this->generateRotuladoMaquinaria($supplierCode, $products);
            default:
                return $this->generateRotuladoGeneral($supplierCode, $products);
        }
    }

    /**
     * Enviar rotulado según el tipo
     */
    private function sendRotuladoByType($tipoRotulado, $supplierCode, $products, $sleepSendMedia)
    {
        switch ($tipoRotulado) {
            case 'rotulado':
                $this->sendRotuladoGeneral($supplierCode, $products, $sleepSendMedia);
                break;
            case 'calzado':
                $this->sendRotuladoCalzado($supplierCode, $products, $sleepSendMedia);
                break;
            case 'ropa':
                $this->sendRotuladoRopa($supplierCode, $products, $sleepSendMedia);
                break;
            case 'ropa_interior':
                $this->sendRotuladoRopaInterior($supplierCode, $products, $sleepSendMedia);
                break;
            case 'maquinaria':
                $this->sendRotuladoMaquinaria($supplierCode, $products, $sleepSendMedia);
                break;
            default:
                $this->sendRotuladoGeneral($supplierCode, $products, $sleepSendMedia);
        }
    }

    /**
     * Obtener ruta del PDF según el tipo de rotulado
     */
    private function getRotuladoPdfPath($tipoRotulado)
    {
        $basePath = storage_path('app/public/templates/rotulado');
        
        switch ($tipoRotulado) {
            case 'rotulado':
                return $basePath . '/rotulado.pdf';
            case 'calzado':
                return $basePath . '/calzado.pdf';
            case 'ropa':
                return $basePath . '/ropa.pdf';
            case 'ropa_interior':
                return $basePath . '/ropa_interior.pdf';
            case 'maquinaria':
                return $basePath . '/maquinaria.pdf';
            default:
                return $basePath . '/rotulado.pdf';
        }
    }

    /**
     * Generar rotulado general
     */
    private function generateRotuladoGeneral($supplierCode, $products)
    {
        $htmlFilePath = public_path('assets/templates/Rotulado_Template.html');
        if (!file_exists($htmlFilePath)) {
            throw new \Exception("No se encontró la plantilla de rotulado general");
        }

        $htmlContent = file_get_contents($htmlFilePath);
        $htmlContent = mb_convert_encoding($htmlContent, 'UTF-8', mb_detect_encoding($htmlContent));

        // Convertir imagen a base64
        $headerImagePath = public_path('assets/templates/ROTULADO_HEADER.png');
        $headerImageBase64 = '';
        if (file_exists($headerImagePath)) {
            $imageData = file_get_contents($headerImagePath);
            $headerImageBase64 = 'data:image/png;base64,' . base64_encode($imageData);
        }

        $htmlContent = str_replace('{{cliente}}', $this->cliente, $htmlContent);
        $htmlContent = str_replace('{{supplier_code}}', $supplierCode, $htmlContent);
        $htmlContent = str_replace('{{carga}}', $this->carga, $htmlContent);
        $htmlContent = str_replace('{{base_url}}/assets/templates/ROTULADO_HEADER.png', $headerImageBase64, $htmlContent);

        return $this->generatePDF($htmlContent);
    }

    /**
     * Generar rotulado para calzado
     */
    private function generateRotuladoCalzado($supplierCode, $products)
    {
        $htmlFilePath = public_path('assets/templates/Rotulado_Calzado_Template.html');
        if (!file_exists($htmlFilePath)) {
            // Si no existe plantilla específica, usar la general
            return $this->generateRotuladoGeneral($supplierCode, $products);
        }

        $htmlContent = file_get_contents($htmlFilePath);
        $htmlContent = mb_convert_encoding($htmlContent, 'UTF-8', mb_detect_encoding($htmlContent));

        // Convertir imagen a base64
        $headerImagePath = public_path('assets/templates/ROTULADO_CALZADO_HEADER.png');
        $headerImageBase64 = '';
        if (file_exists($headerImagePath)) {
            $imageData = file_get_contents($headerImagePath);
            $headerImageBase64 = 'data:image/png;base64,' . base64_encode($imageData);
        }

        $htmlContent = str_replace('{{cliente}}', $this->cliente, $htmlContent);
        $htmlContent = str_replace('{{supplier_code}}', $supplierCode, $htmlContent);
        $htmlContent = str_replace('{{carga}}', $this->carga, $htmlContent);
        $htmlContent = str_replace('{{base_url}}/assets/templates/ROTULADO_CALZADO_HEADER.png', $headerImageBase64, $htmlContent);

        return $this->generatePDF($htmlContent);
    }

    /**
     * Generar rotulado para ropa
     */
    private function generateRotuladoRopa($supplierCode, $products)
    {
        $htmlFilePath = public_path('assets/templates/Rotulado_Ropa_Template.html');
        if (!file_exists($htmlFilePath)) {
            return $this->generateRotuladoGeneral($supplierCode, $products);
        }

        $htmlContent = file_get_contents($htmlFilePath);
        $htmlContent = mb_convert_encoding($htmlContent, 'UTF-8', mb_detect_encoding($htmlContent));

        $headerImagePath = public_path('assets/templates/ROTULADO_ROPA_HEADER.png');
        $headerImageBase64 = '';
        if (file_exists($headerImagePath)) {
            $imageData = file_get_contents($headerImagePath);
            $headerImageBase64 = 'data:image/png;base64,' . base64_encode($imageData);
        }

        $htmlContent = str_replace('{{cliente}}', $this->cliente, $htmlContent);
        $htmlContent = str_replace('{{supplier_code}}', $supplierCode, $htmlContent);
        $htmlContent = str_replace('{{carga}}', $this->carga, $htmlContent);
        $htmlContent = str_replace('{{base_url}}/assets/templates/ROTULADO_ROPA_HEADER.png', $headerImageBase64, $htmlContent);

        return $this->generatePDF($htmlContent);
    }

    /**
     * Generar rotulado para ropa interior
     */
    private function generateRotuladoRopaInterior($supplierCode, $products)
    {
        $htmlFilePath = public_path('assets/templates/Rotulado_RopaInterior_Template.html');
        if (!file_exists($htmlFilePath)) {
            return $this->generateRotuladoGeneral($supplierCode, $products);
        }

        $htmlContent = file_get_contents($htmlFilePath);
        $htmlContent = mb_convert_encoding($htmlContent, 'UTF-8', mb_detect_encoding($htmlContent));

        $headerImagePath = public_path('assets/templates/ROTULADO_ROPA_INTERIOR_HEADER.png');
        $headerImageBase64 = '';
        if (file_exists($headerImagePath)) {
            $imageData = file_get_contents($headerImagePath);
            $headerImageBase64 = 'data:image/png;base64,' . base64_encode($imageData);
        }

        $htmlContent = str_replace('{{cliente}}', $this->cliente, $htmlContent);
        $htmlContent = str_replace('{{supplier_code}}', $supplierCode, $htmlContent);
        $htmlContent = str_replace('{{carga}}', $this->carga, $htmlContent);
        $htmlContent = str_replace('{{base_url}}/assets/templates/ROTULADO_ROPA_INTERIOR_HEADER.png', $headerImageBase64, $htmlContent);

        return $this->generatePDF($htmlContent);
    }

    /**
     * Generar rotulado para maquinaria
     */
    private function generateRotuladoMaquinaria($supplierCode, $products)
    {
        $htmlFilePath = public_path('assets/templates/Rotulado_Maquinaria_Template.html');
        if (!file_exists($htmlFilePath)) {
            return $this->generateRotuladoGeneral($supplierCode, $products);
        }

        $htmlContent = file_get_contents($htmlFilePath);
        $htmlContent = mb_convert_encoding($htmlContent, 'UTF-8', mb_detect_encoding($htmlContent));

        $headerImagePath = public_path('assets/templates/ROTULADO_MAQUINARIA_HEADER.png');
        $headerImageBase64 = '';
        if (file_exists($headerImagePath)) {
            $imageData = file_get_contents($headerImagePath);
            $headerImageBase64 = 'data:image/png;base64,' . base64_encode($imageData);
        }

        $htmlContent = str_replace('{{cliente}}', $this->cliente, $htmlContent);
        $htmlContent = str_replace('{{supplier_code}}', $supplierCode, $htmlContent);
        $htmlContent = str_replace('{{carga}}', $this->carga, $htmlContent);
        $htmlContent = str_replace('{{base_url}}/assets/templates/ROTULADO_MAQUINARIA_HEADER.png', $headerImageBase64, $htmlContent);

        return $this->generatePDF($htmlContent);
    }

    /**
     * Generar PDF desde HTML
     */
    private function generatePDF($htmlContent)
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', false);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('chroot', public_path());
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($htmlContent);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Enviar rotulado general
     */
    private function sendRotuladoGeneral($supplierCode, $products, $sleepSendMedia)
    {
        // Para rotulado general, no enviar PDF adicional
        Log::info('Enviando rotulado general para proveedor: ' . $supplierCode);
    }

    /**
     * Enviar rotulado para calzado
     */
    private function sendRotuladoCalzado($supplierCode, $products, $sleepSendMedia)
    {
        $message = "⚠ Atención ⚠\n\nEtiqueta especial: Calzado\n\nSegún la regulación de Aduanas Perú todo calzado requiere tener una etiqueta Irremovible (Cosida a la lengüeta) de manera obligatoria. \n\nPor lo tanto, dile a tu proveedor #{$supplierCode} que le ponga la etiqueta.\n\n⛔ No aceptamos cargas sin el etiquetado correcto ya que la aduana lo puede decomisar.\n🚫 El rotulado NO puede estar en Chino deberá ser en ESPAÑOL.\n📝 Aquí tienes un ejemplo de como debes colocar las etiquetas👇🏼";
        
        // Enviar PDF específico para calzado
        $calzadoPdfPath = $this->getRotuladoPdfPath('calzado');
        if (file_exists($calzadoPdfPath)) {
            $this->sendMedia($calzadoPdfPath, 'application/pdf', $message, null, $sleepSendMedia);
        } else {
            Log::warning('No se encontró PDF específico para calzado: ' . $calzadoPdfPath);
        }
    }

    /**
     * Enviar rotulado para ropa
     */
    private function sendRotuladoRopa($supplierCode, $products, $sleepSendMedia)
    {
        $message = "⚠ Atención ⚠\n\nEtiqueta especial: Prendas de Vestir\n\nSegún la regulación de Aduanas - Perú todo producto textil, requiere tener un etiqueta Cosida o Sublimada de manera obligatoria. \n\nPor lo tanto, dile a tu proveedor #{$supplierCode} que le ponga la etiqueta.\n\n⛔ No aceptamos cargas sin el etiquetado correcto ya que la aduana lo puede decomisar.\n🚫 El rotulado NO puede estar en Chino deberá ser en ESPAÑOL.\n📝Aquí tienes un ejemplo de como tu proveedor debe colocar las etiquetas👇🏼";

        // Enviar PDF específico para ropa
        $ropaPdfPath = $this->getRotuladoPdfPath('ropa');
        if (file_exists($ropaPdfPath)) {
            $this->sendMedia($ropaPdfPath, 'application/pdf', $message, null, $sleepSendMedia);
        } else {
            Log::warning('No se encontró PDF específico para ropa: ' . $ropaPdfPath);
        }
    }

    /**
     * Enviar rotulado para ropa interior
     */
    private function sendRotuladoRopaInterior($supplierCode, $products, $sleepSendMedia)
    {
        $message = "⚠ Atención ⚠\n\nEtiqueta especial: Ropa interior/ Accesorios de Vestir\n\nSegún la regulación de Aduanas - Perú todo producto textil, requiere tener un etiqueta Cosida o Colgante de manera obligatoria. \n\nPor lo tanto, dile a tu proveedor #{$supplierCode} que le ponga la etiqueta.\n\n⛔ No aceptamos cargas sin el etiquetado correcto ya que la aduana lo puede decomisar.\n🚫 El rotulado NO puede estar en Chino deberá ser en ESPAÑOL.\n📝 Aquí tienes un ejemplo de como tu proveedor debe colocar las etiquetas👇🏼";

        // Enviar PDF específico para ropa interior
        $ropaInteriorPdfPath = $this->getRotuladoPdfPath('ropa_interior');
        if (file_exists($ropaInteriorPdfPath)) {
            $this->sendMedia($ropaInteriorPdfPath, 'application/pdf', $message, null, $sleepSendMedia);
        } else {
            Log::warning('No se encontró PDF específico para ropa interior: ' . $ropaInteriorPdfPath);
        }
    }

    /**
     * Enviar rotulado para maquinaria
     */
    private function sendRotuladoMaquinaria($supplierCode, $products, $sleepSendMedia)
    {
        $message = "⚠ Atención ⚠\n\nEtiqueta especial: Maquinaria\n\nSegún la regulación de Aduanas - Perú todas maquinaria domestico o industrial que contengan un motor eléctrico, requiere tener una placa Irremovible y visible de manera obligatoria. \n\nPor lo tanto, dile a tu proveedor #{$supplierCode} que le ponga la etiqueta.\n\n⛔ No aceptamos cargas sin la placa ya que la aduana lo puede observar o decomisar.\n🚫 El rotulado del producto NO puede estar en Chino deberá ser en ESPAÑOL.\n📝 Aquí tienes un ejemplo de como tu proveedor debe colocar la placa👇🏼";

        // Enviar PDF específico para maquinaria
        $maquinariaPdfPath = $this->getRotuladoPdfPath('maquinaria');
        if (file_exists($maquinariaPdfPath)) {
            $this->sendMedia($maquinariaPdfPath, 'application/pdf', $message, null, $sleepSendMedia);
        } else {
            Log::warning('No se encontró PDF específico para maquinaria: ' . $maquinariaPdfPath);
        }
    }
}
