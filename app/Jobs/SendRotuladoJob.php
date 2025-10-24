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
use App\Traits\GoogleSheetsHelper;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SendRotuladoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait, GoogleSheetsHelper;

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
                    $providersHasNoSended[] = $proveedor;
                    continue;
                }

                $proveedorDB = $proveedoresFromDB->get($proveedor['id']);
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
                    $proveedorDB->update(["send_rotulado_status" => "SENDED", 'estados' => 'ROTULADO']);

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
        return $this->generateRotuladoGeneral($supplierCode, $products);
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
            case 'movilidad_personal':
                $this->sendRotuladoMovilidadPersonal($supplierCode, $products, $sleepSendMedia);
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
    private function generateRotuladoMovilidadPersonal($supplierCode, $products)
    {
        $htmlFilePath = public_path('assets/templates/Rotulado_MovilidadPersonal_Template.html');
        if (!file_exists($htmlFilePath)) {
            return $this->generateRotuladoGeneral($supplierCode, $products);
        }

        $htmlContent = file_get_contents($htmlFilePath);
        $htmlContent = mb_convert_encoding($htmlContent, 'UTF-8', mb_detect_encoding($htmlContent));

        $headerImagePath = public_path('assets/templates/ROTULADO_MOVILIDAD_PERSONAL_HEADER.png');
        $headerImageBase64 = '';
        if (file_exists($headerImagePath)) {
            $imageData = file_get_contents($headerImagePath);
            $headerImageBase64 = 'data:image/png;base64,' . base64_encode($imageData);
        }

        $htmlContent = str_replace('{{cliente}}', $this->cliente, $htmlContent);
        $htmlContent = str_replace('{{supplier_code}}', $supplierCode, $htmlContent);
        $htmlContent = str_replace('{{carga}}', $this->carga, $htmlContent);
        $htmlContent = str_replace('{{base_url}}/assets/templates/ROTULADO_MOVILIDAD_PERSONAL_HEADER.png', $headerImageBase64, $htmlContent);

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
        $message = "👆🏻 ⚠ Atención ⚠\n\nEtiqueta especial: Prendas de Vestir\n\nSegún la regulación de Aduanas - Perú todo producto textil, requiere tener un etiqueta Cosida o Sublimada de manera obligatoria. \n\nPor lo tanto, dile a tu proveedor #{$supplierCode} que le ponga la etiqueta.\n\n⛔ No aceptamos cargas sin el etiquetado correcto ya que la aduana lo puede decomisar.\n🚫 El rotulado NO puede estar en Chino deberá ser en ESPAÑOL.\n📝Aquí tienes un ejemplo de como tu proveedor debe colocar las etiquetas";

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
        $message = "👆🏻 ⚠ Atención ⚠\n\nEtiqueta especial: Ropa interior/ Accesorios de Vestir\n\nSegún la regulación de Aduanas - Perú todo producto textil, requiere tener un etiqueta Cosida o Colgante de manera obligatoria. \n\nPor lo tanto, dile a tu proveedor #{$supplierCode} que le ponga la etiqueta.\n\n⛔ No aceptamos cargas sin el etiquetado correcto ya que la aduana lo puede decomisar.\n🚫 El rotulado NO puede estar en Chino deberá ser en ESPAÑOL.\n📝 Aquí tienes un ejemplo de como tu proveedor debe colocar las etiquetas";

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
        $message = "👆🏻 ⚠ Atención ⚠\n\nEtiqueta especial: Maquinaria\n\nSegún la regulación de Aduanas - Perú todas maquinaria domestico o industrial que contengan un motor eléctrico, requiere tener una placa Irremovible y visible de manera obligatoria. \n\nPor lo tanto, dile a tu proveedor #{$supplierCode} que le ponga la etiqueta.\n\n⛔ No aceptamos cargas sin la placa ya que la aduana lo puede observar o decomisar.\n🚫 El rotulado del producto NO puede estar en Chino deberá ser en ESPAÑOL.\n📝 Aquí tienes un ejemplo de como tu proveedor debe colocar la placa";

        // Enviar PDF específico para maquinaria
        $maquinariaPdfPath = $this->getRotuladoPdfPath('maquinaria');
        if (file_exists($maquinariaPdfPath)) {
            $this->sendMedia($maquinariaPdfPath, 'application/pdf', $message, null, $sleepSendMedia);
        } else {
            Log::warning('No se encontró PDF específico para maquinaria: ' . $maquinariaPdfPath);
        }
    }

    private function sendRotuladoMovilidadPersonal($supplierCode, $products, $sleepSendMedia)
    {
        try {
            // Obtener información de la cotización
            $cotizacionInfo = Cotizacion::where('id', $this->idCotizacion)->first();
            if (!$cotizacionInfo) {
                Log::error('No se encontró la cotización con ID: ' . $this->idCotizacion);
                return;
            }

            // Obtener qty_box del proveedor desde la base de datos
            $proveedorDB = CotizacionProveedor::where('code_supplier', $supplierCode)
                ->where('id_cotizacion', $this->idCotizacion)
                ->first();
            
            if (!$proveedorDB) {
                Log::error('No se encontró el proveedor en BD: ' . $supplierCode);
                return;
            }

            $qtyBox = (int) $proveedorDB->qty_box;
            if ($qtyBox <= 0) {
                Log::warning('qty_box no válido para movilidad personal: ' . $qtyBox);
                return;
            }

            Log::info("Procesando movilidad personal - qty_box: {$qtyBox}, cliente: {$cotizacionInfo->nombre}");

            // Obtener la última fila con datos y el código de la columna F
            $lastRowData = $this->getLastRowWithData();
            if (!$lastRowData || !isset($lastRowData['code'])) {
                Log::error('No se pudo obtener la última fila con datos');
                return;
            }

            $lastCode = $lastRowData['code'];
            $lastRowNumber = $lastRowData['row'];
            
            Log::info("Código base encontrado: {$lastCode} en fila: {$lastRowNumber}");

            // Generar códigos correlativos
            $codes = $this->generateCorrelativeCodes($lastCode, $qtyBox);
            
            // Agregar filas al Google Sheet debajo de la última fila con datos
            $this->addRowsToGoogleSheet($cotizacionInfo->nombre, $codes, $qtyBox, $lastRowNumber);

            // Procesar plantilla VIM
            $excelPath = $this->processVimTemplate($cotizacionInfo->nombre, $codes);

            // Enviar archivo por WhatsApp
            $message = "👆🏻 ⚠ Atención ⚠
Etiqueta especial: Movilidad Personal

Según la regulación de Aduanas - Perú todos los Scooters / Monociclos /Bicimotos / Trimotos requiere tener código VIN y Motor grabado en el producto de manera obligatoria.

Por lo tanto, dile a tu proveedor #{$supplierCode} que le ponga la etiqueta.

⛔ No aceptamos cargas sin código VIN o Motor ya que la aduana lo puede observar o decomisar.
📝 Aquí tienes el archivo con los códigos generados";

            if (file_exists($excelPath)) {
                $this->sendMedia($excelPath, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $message, null, $sleepSendMedia);
                Log::info('Archivo VIM enviado por WhatsApp exitosamente');
            } else {
                Log::error('No se pudo crear el archivo VIM: ' . $excelPath);
            }

        } catch (\Exception $e) {
            Log::error('Error en sendRotuladoMovilidadPersonal: ' . $e->getMessage());
        }
    }

    /**
     * Obtener la última fila con datos y el código de la columna F
     */
    private function getLastRowWithData()
    {
        try {
            // Obtener valores de toda la hoja para encontrar la última fila con datos
            $values = $this->getRangeValues('A1:Z1000');
            
            if (empty($values)) {
                return null;
            }

            // Buscar la última fila que tenga datos en cualquier columna
            $lastRowIndex = 0;
            $lastRowData = null;
            
            foreach ($values as $rowIndex => $row) {
                // Verificar si la fila tiene algún dato
                $hasData = false;
                foreach ($row as $cell) {
                    if (!empty($cell)) {
                        $hasData = true;
                        break;
                    }
                }
                
                if ($hasData) {
                    $lastRowIndex = $rowIndex;
                    $lastRowData = $row;
                }
            }

            $lastRowNumber = $lastRowIndex + 1; // +1 porque Google Sheets usa índice base 1
            
            // Obtener el código de la columna F de la última fila
            $lastCode = isset($lastRowData[5]) ? $lastRowData[5] : null; // Columna F es índice 5
            
            Log::info("Última fila con datos: {$lastRowNumber}, código en columna F: {$lastCode}");
            
            return [
                'row' => $lastRowNumber,
                'code' => $lastCode,
                'rowData' => $lastRowData
            ];

        } catch (\Exception $e) {
            Log::error('Error obteniendo última fila con datos: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generar códigos correlativos basados en el código base
     */
    private function generateCorrelativeCodes($baseCode, $qtyBox, $lastCodeRow = null)
    {
        try {
            // Extraer el número del código base (ej: L7NES211MSG083495 -> 083495)
            if (!preg_match('/^L7NES(\d+)MSG(\d+)$/', $baseCode, $matches)) {
                throw new \Exception('Formato de código base inválido: ' . $baseCode);
            }

            $baseNumber = (int) $matches[2];
            $codes = [];

            // Siempre usar el número del código base + 1, no el número de fila
            $startNumber = $baseNumber + 1;

            for ($i = 0; $i < $qtyBox; $i++) {
                $newNumber = $startNumber + $i;
                $newCode = sprintf('L7NES%sMSG%06d', $matches[1], $newNumber);
                $codes[] = $newCode;
            }

            Log::info('Códigos generados: ' . implode(', ', $codes));
            return $codes;

        } catch (\Exception $e) {
            Log::error('Error generando códigos correlativos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Agregar filas al Google Sheet con el nombre del cliente en columna B, índice en columna C y códigos en columna F
     */
    private function addRowsToGoogleSheet($clienteNombre, $codes, $qtyBox, $lastRowWithData)
    {
        try {
            // Empezar desde la fila siguiente a la última fila con datos
            $startRow = $lastRowWithData + 1;

            // Insertar nombre del cliente en la columna B
            $bData = [];
            for ($i = 0; $i < $qtyBox; $i++) {
                $bData[] = [$clienteNombre];
            }
            $bRange = "B{$startRow}:B" . ($startRow + $qtyBox - 1);
            $this->insertRangeValues($bRange, $bData);

            // Insertar índice en la columna C (1, 2, 3, etc.)
            $cData = [];
            for ($i = 0; $i < $qtyBox; $i++) {
                $cData[] = [$i + 1]; // Empezar desde 1
            }
            $cRange = "C{$startRow}:C" . ($startRow + $qtyBox - 1);
            $this->insertRangeValues($cRange, $cData);

            // Insertar códigos en la columna F
            $fData = [];
            for ($i = 0; $i < $qtyBox; $i++) {
                $fData[] = [$codes[$i]];
            }
            $fRange = "F{$startRow}:F" . ($startRow + $qtyBox - 1);
            $this->insertRangeValues($fRange, $fData);

            // Mergear celdas de la columna B para el nombre del cliente
            if ($qtyBox > 1) {
                $this->mergeCells("B{$startRow}", "B" . ($startRow + $qtyBox - 1));
            }

            // Aplicar bordes desde columna B hasta G
            $this->applyBordersToRows($startRow, $startRow + $qtyBox - 1, 'B', 'G');

            Log::info("Filas agregadas al Google Sheet: {$qtyBox} filas desde la fila {$startRow} - Nombre en columna B, índice en columna C, códigos en columna F");

        } catch (\Exception $e) {
            Log::error('Error agregando filas al Google Sheet: ' . $e->getMessage());
        }
    }

    /**
     * Obtener el número de la última fila ocupada
     */
    private function getLastRowNumber()
    {
        try {
            $values = $this->getRangeValues('A1:A1000');
            $lastRow = 0;
            
            foreach ($values as $index => $row) {
                if (!empty($row[0])) {
                    $lastRow = $index + 1;
                }
            }
            
            return $lastRow;
        } catch (\Exception $e) {
            Log::error('Error obteniendo última fila: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener la letra de la última columna ocupada
     */
    private function getLastColumnLetter()
    {
        try {
            // Obtener valores de la primera fila para detectar la última columna ocupada
            $values = $this->getRangeValues('A1:ZZ1');
            
            if (empty($values) || empty($values[0])) {
                return 'A'; // Si no hay datos, empezar desde A
            }

            $lastColumnIndex = 0;
            $firstRow = $values[0];
            
            // Buscar la última columna con datos
            foreach ($firstRow as $index => $value) {
                if (!empty($value)) {
                    $lastColumnIndex = $index;
                }
            }
            
            $lastColumn = $this->columnIndexToLetter($lastColumnIndex);
            Log::info("Última columna ocupada: {$lastColumn}");
            return $lastColumn;

        } catch (\Exception $e) {
            Log::error('Error obteniendo última columna: ' . $e->getMessage());
            return 'A'; // Fallback a columna A
        }
    }

    /**
     * Obtener la siguiente letra de columna
     */
    private function getNextColumnLetter($currentColumn)
    {
        $index = $this->letterToColumnIndex($currentColumn);
        return $this->columnIndexToLetter($index + 1);
    }

    /**
     * Procesar la plantilla VIM y agregar los datos
     */
    private function processVimTemplate($clienteNombre, $codes)
    {
        try {
            $templatePath = public_path('assets/templates/PlantillaVim.xlsx');
            
            if (!file_exists($templatePath)) {
                throw new \Exception('Plantilla VIM no encontrada: ' . $templatePath);
            }

            // Crear directorio temporal si no existe
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Crear una copia temporal
            $tempPath = $tempDir . '/vim_' . time() . '.xlsx';
            copy($templatePath, $tempPath);

            // Usar PhpSpreadsheet para modificar el archivo
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($tempPath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Empezar desde la fila 3
            $startRow = 3;
            
            // Agregar datos en las columnas B y F
            foreach ($codes as $index => $code) {
                $row = $startRow + $index;
                
                // Columna B: Nombre del cliente
                $worksheet->setCellValue("B{$row}", $clienteNombre);
                
                // Columna F: Código
                $worksheet->setCellValue("F{$row}", $code);
            }

            // Guardar el archivo modificado
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($tempPath);

            Log::info('Plantilla VIM procesada exitosamente: ' . $tempPath);
            return $tempPath;

        } catch (\Exception $e) {
            Log::error('Error procesando plantilla VIM: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Aplicar bordes a un rango de filas en el Google Sheet
     */
    private function applyBordersToRows($startRow, $endRow, $startColumn = 'A', $endColumn = null)
    {
        try {
            if (!$this->initializeGoogleSheets()) {
                throw new \Exception('No se pudo inicializar Google Sheets');
            }

            // Obtener el ID de la hoja
            $sheetId = $this->getSheetId();
            
            // Si no se especifica columna final, usar la última columna ocupada
            if (!$endColumn) {
                $lastColumn = $this->getLastColumnLetter();
                $endColumnIndex = $this->letterToColumnIndex($lastColumn) + 1;
            } else {
                $endColumnIndex = $this->letterToColumnIndex($endColumn) + 1;
            }

            $startColumnIndex = $this->letterToColumnIndex($startColumn);

            // Crear el rango para aplicar bordes
            $range = new \Google\Service\Sheets\GridRange([
                'sheetId' => $sheetId,
                'startRowIndex' => $startRow - 1, // Convertir a índice base 0
                'endRowIndex' => $endRow, // Ya está en índice base 0
                'startColumnIndex' => $startColumnIndex,
                'endColumnIndex' => $endColumnIndex
            ]);

            // Crear el estilo de borde
            $borderStyle = new \Google\Service\Sheets\Border([
                'style' => 'SOLID',
                'width' => 1,
                'color' => new \Google\Service\Sheets\Color([
                    'red' => 0.0,
                    'green' => 0.0,
                    'blue' => 0.0
                ])
            ]);

            // Crear los bordes para todas las direcciones
            $borders = new \Google\Service\Sheets\Borders([
                'top' => $borderStyle,
                'bottom' => $borderStyle,
                'left' => $borderStyle,
                'right' => $borderStyle
            ]);

            // Crear el estilo de celda
            $cellFormat = new \Google\Service\Sheets\CellFormat([
                'borders' => $borders
            ]);

            // Crear la solicitud de formato
            $formatRequest = new \Google\Service\Sheets\Request([
                'repeatCell' => new \Google\Service\Sheets\RepeatCellRequest([
                    'range' => $range,
                    'cell' => new \Google\Service\Sheets\CellData([
                        'userEnteredFormat' => $cellFormat
                    ]),
                    'fields' => 'userEnteredFormat.borders'
                ])
            ]);

            // Ejecutar la solicitud
            $batchRequest = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                'requests' => [$formatRequest]
            ]);

            $result = $this->googleService->spreadsheets->batchUpdate($this->spreadsheetId, $batchRequest);

            Log::info("Bordes aplicados a las filas {$startRow}-{$endRow} desde columna {$startColumn} hasta {$endColumn}");

        } catch (\Exception $e) {
            Log::error("Error aplicando bordes a filas {$startRow}-{$endRow}: " . $e->getMessage());
        }
    }
}
