<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Spatie\Browsershot\Browsershot;
use App\Models\CargaConsolidada\Contenedor;

class ResumenCostosImageService
{
    /**
     * Generar imagen del resumen de costos
     */
    public function generateResumenCostosImage($calculadora)
    {
        try {
            // Generar HTML con datos dinámicos
            $html = $this->generateHTML($calculadora);

            // Convertir HTML a imagen usando wkhtmltopdf
            $imagePath = $this->convertHTMLToImage($html, $calculadora);

            if ($imagePath && file_exists($imagePath)) {
                return [
                    'path' => $imagePath,
                    'filename' => basename($imagePath),
                    'url' => Storage::url('resumen_costos/' . basename($imagePath))
                ];
            }

            throw new \Exception('No se pudo generar la imagen del resumen de costos');
        } catch (\Exception $e) {
            Log::error('Error al generar imagen del resumen de costos: ' . $e->getMessage(), [
                'calculadora_id' => $calculadora->id,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Generar HTML con datos dinámicos de la calculadora
     */
    private function generateHTML($calculadora)
    {
        //get calculadora id_carga_consolidada_contenedor and get carga_consolidada_contenedor
        $cargaConsolidadaContenedor = Contenedor::find($calculadora->id_carga_consolidada_contenedor);
        $fechaArribo=$cargaConsolidadaContenedor->fecha_cierre;
        $fechaCorte=$cargaConsolidadaContenedor->fecha_puerto;     
        $urlCotizacion = $calculadora->url_cotizacion;
        $urlCotizacion = public_path($urlCotizacion);
        $objPHPExcel = \PhpOffice\PhpSpreadsheet\IOFactory::load($urlCotizacion);
        $sheet = $objPHPExcel->getSheet(0);
        $productos = [];
        $endWhile=false;
        $i=37;
        
        while(!$endWhile){
            if($sheet->getCell('A' . $i)->getValue() == 'TOTAL'){
                $endWhile=true;
                continue;
            }
            $producto = [];
            $producto['nombre'] = $sheet->getCell('B' . $i)->getCalculatedValue();
            $producto['cantidad'] = $sheet->getCell('E' . $i)->getCalculatedValue();
            $producto['costo_unitario'] = $sheet->getCell('F' . $i)->getCalculatedValue();
            $producto['precio_unitario'] = $sheet->getCell('H' . $i)->getCalculatedValue();
            $producto['total'] = $sheet->getCell('I' . $i)->getCalculatedValue();
            $producto['precio_unitario_soles'] = $sheet->getCell('J' . $i)->getCalculatedValue();
            $productos[] = $producto;
            $i++;
        }
      

        // Fechas (ejemplo)
        
        // Generar filas de productos
        $productosHTML = '';
        $index = 1;
        //primer pago J31, segundo pago J32 , inversion total J31+J32
        $primerPago = $objPHPExcel->getSheet(0)->getCell('J31')->getCalculatedValue();
        $segundoPago = $objPHPExcel->getSheet(0)->getCell('J32')->getCalculatedValue();
        $inversionTotal = $primerPago + $segundoPago;
        //foreach productos
        foreach ($productos as $producto) {
            $productosHTML .= "
            <tr>
                <td>{$index}</td>
                <td>{$producto['nombre']}</td>
                <td>{$producto['costo_unitario']}</td>
                <td>{$producto['precio_unitario']}</td>
                <td>{$producto['precio_unitario_soles']}</td>
            </tr>";
            $index++;
        }

        $html = $this->getHTMLTemplate();

        // Reemplazar valores dinámicos
        $html = str_replace('{{items}}', $productosHTML, $html);
        $html = str_replace('{{primer_pago}}', number_format($primerPago, 2, '.', ','), $html);
        $html = str_replace('{{segundo_pago}}', number_format($segundoPago, 2, '.', ','), $html);
        $html = str_replace('{{fecha_corte}}', $fechaCorte, $html);
        $html = str_replace('{{fecha_arribo}}', $fechaArribo, $html);
        $html = str_replace('{{inversion_total}}', number_format($inversionTotal, 2, '.', ','), $html);

        return $html;
    }

    /**
     * Obtener template HTML base
     */
    private function getHTMLTemplate()
    {
        return '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen de Costos Unitarios</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <!-- Tabla de Costos Unitarios -->
        <div class="cost-summary-section">
            <table class="cost-table">
                <thead>
                    <tr class="header-row">
                        <th colspan="5">RESUMEN DE COSTOS UNITARIOS</th>
                    </tr>
                    <tr class="subheader-row">
                        <th># ÍTEM</th>
                        <th>NOMBRE PRODUCTO</th>
                        <th>VALOR CHINA USD</th>
                        <th>VALOR PERÚ USD</th>
                        <th>VALOR PERÚ SOLES</th>
                    </tr>
                </thead>
                <tbody>
                    {{items}}
                </tbody>
            </table>
        </div>

        <!-- Sección de Pagos y Fechas -->
        <div class="payments-section">
            <div class="payments-container">
                <!-- Pagos de Servicio -->
                <div class="payment-summary">
                    <div class="payment-header">
                        RESUMEN DE PAGOS DE SERVICIO DE IMPORTACIÓN
                    </div>
                    <div class="payment-item">
                        <span class="payment-label">PRIMER PAGO</span>
                        <span class="payment-amount">$ {{primer_pago}}</span>
                    </div>
                    <div class="payment-item">
                        <span class="payment-label">SEGUNDO PAGO</span>
                        <span class="payment-amount">$ {{segundo_pago}}</span>
                    </div>
                </div>

                <!-- Fechas -->
                <div class="dates-section">
                    <div class="dates-header">FECHAS:</div>
                    <div class="date-item">
                        <div class="date-description">Servicio de Consolidado antes de la Fecha de Corte</div>
                    </div>
                    <div class="date-item">
                        <div class="date-description">Pago de Impuestos antes del Arribo 26/09</div>
                    </div>
                </div>
            </div>

            <!-- Inversión Total -->
            <div class="total-investment">
                <div class="investment-row">
                    <span class="investment-label">INVERSIÓN TOTAL</span>
                    <span class="investment-amount">$ {{inversion_total}}</span>
                    <span class="investment-description">
                        Incluye Valor de Carga + Servicio de Consolidado + Pago de Impuestos
                    </span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<style>
    * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f5f5f5;
    padding: 20px;
    color: #333;
}

.container {
    max-width: 1000px;
    margin: 0 auto;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

/* Tabla de Costos Unitarios */
.cost-summary-section {
    margin-bottom: 0;
}

.cost-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.header-row th {
    background-color: #4a4a4a;
    color: white;
    padding: 12px 8px;
    text-align: center;
    font-weight: bold;
    font-size: 16px;
    letter-spacing: 0.5px;
}

.subheader-row th {
    background-color: #6a6a6a;
    color: white;
    padding: 10px 8px;
    text-align: center;
    font-weight: 600;
    font-size: 13px;
    border-right: 1px solid #8a8a8a;
}

.subheader-row th:last-child {
    border-right: none;
}

.data-row td {
    padding: 12px 8px;
    text-align: center;
    border: 1px solid #ddd;
    font-weight: 500;
}

.data-row td:first-child {
    background-color: #f8f9fa;
    font-weight: bold;
}

.data-row td:nth-child(2) {
    background-color: #f8f9fa;
    font-weight: bold;
}

/* Sección de Pagos */
.payments-section {
    background-color: white;
}

.payments-container {
    display: flex;
    width: 100%;
}

.payment-summary {
    flex: 1;
    border: 1px solid #ddd;
    border-top: none;
}

.payment-header {
    background-color: #e67e22;
    color: white;
    padding: 12px;
    text-align: center;
    font-weight: bold;
    font-size: 14px;
    letter-spacing: 0.3px;
}

.payment-item {
    display: flex;
    padding: 12px;
    border-bottom: 1px solid #ddd;
    align-items: center;
}

.payment-item:last-child {
    border-bottom: none;
}

.payment-label {
    flex: 1;
    font-weight: 600;
    color: #333;
}

.payment-amount {
    font-weight: bold;
    color: #2c3e50;
    min-width: 100px;
    text-align: right;
}

/* Sección de Fechas */
.dates-section {
    flex: 1;
    border: 1px solid #ddd;
    border-top: none;
    border-left: none;
}

.dates-header {
    background-color: #95a5a6;
    color: white;
    padding: 12px;
    text-align: center;
    font-weight: bold;
    font-size: 14px;
    letter-spacing: 0.3px;
}

.date-item {
    padding: 12px;
    border-bottom: 1px solid #ddd;
    min-height: 48px;
    display: flex;
    align-items: center;
}

.date-item:last-child {
    border-bottom: none;
}

.date-description {
    font-size: 13px;
    color: #555;
    line-height: 1.4;
}

/* Inversión Total */
.total-investment {
    background-color: #e67e22;
    border: 1px solid #ddd;
    border-top: none;
}

.investment-row {
    display: flex;
    align-items: center;
    padding: 15px 12px;
    color: white;
}

.investment-label {
    font-weight: bold;
    font-size: 16px;
    margin-right: 20px;
    min-width: 150px;
}

.investment-amount {
    font-weight: bold;
    font-size: 18px;
    margin-right: 20px;
    min-width: 120px;
}

.investment-description {
    font-size: 13px;
    opacity: 0.95;
    line-height: 1.3;
}

/* Responsive Design */
@media (max-width: 768px) {
    .container {
        margin: 10px;
        border-radius: 4px;
    }
    
    .payments-container {
        flex-direction: column;
    }
    
    .dates-section {
        border-left: 1px solid #ddd;
    }
    
    .investment-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .investment-label,
    .investment-amount {
        min-width: auto;
    }
    
    .cost-table {
        font-size: 12px;
    }
    
    .subheader-row th,
    .data-row td {
        padding: 8px 4px;
    }
}

@media (max-width: 480px) {
    body {
        padding: 10px;
    }
    
    .cost-table {
        font-size: 11px;
    }
    
    .header-row th {
        font-size: 14px;
        padding: 10px 4px;
    }
    
    .subheader-row th {
        font-size: 11px;
        padding: 8px 2px;
    }
    
    .data-row td {
        padding: 10px 4px;
    }
    
    .payment-item {
        padding: 10px 8px;
    }
    
    .date-item {
        padding: 10px 8px;
    }
    
    .investment-row {
        padding: 12px 8px;
    }
}
</style>';
    }

    /**
     * Convertir HTML a imagen usando Chrome headless o alternativa
     */
    private function convertHTMLToImage($html, $calculadora)
    {
        try {
            // Crear directorio si no existe
            $directory = storage_path('app/public/resumen_costos');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Generar nombre de archivo único
            $timestamp = now()->format('Y_m_d_H_i_s');
            $filename = "RESUMEN_COSTOS_{$calculadora->nombre_cliente}_{$timestamp}.png";
            $imagePath = $directory . '/' . $filename;

            // Guardar HTML temporalmente
            $tempHtmlPath = tempnam(sys_get_temp_dir(), 'resumen_costos_') . '.html';
            file_put_contents($tempHtmlPath, $html);

            Log::info('Iniciando conversión HTML a imagen', [
                'calculadora_id' => $calculadora->id,
                'temp_html_path' => $tempHtmlPath,
                'target_image_path' => $imagePath
            ]);

            // Verificar métodos disponibles
            $chromeAvailable = $this->isChromeAvailable();
            $wkhtmltoimageAvailable = $this->isWkhtmltoimageAvailable();

            Log::info('Métodos de conversión disponibles', [
                'chrome_available' => $chromeAvailable,
                'wkhtmltoimage_available' => $wkhtmltoimageAvailable
            ]);

            // Intentar diferentes métodos de conversión
            $success = false;
            $methodUsed = 'none';

            // Método 1: Chrome headless (recomendado)
            if ($chromeAvailable) {
                Log::info('Intentando conversión con Chrome headless');
                $success = $this->convertWithChrome($tempHtmlPath, $imagePath);
                if ($success) {
                    $methodUsed = 'chrome';
                    Log::info('Conversión exitosa con Chrome headless');
                }
            }

            // Método 2: wkhtmltoimage (si está disponible)
            if (!$success && $wkhtmltoimageAvailable) {
                Log::info('Intentando conversión con wkhtmltoimage');
                $success = $this->convertWithWkhtmltoimage($tempHtmlPath, $imagePath);
                if ($success) {
                    $methodUsed = 'wkhtmltoimage';
                    Log::info('Conversión exitosa con wkhtmltoimage');
                }
            }

            // Método 3: Browsershot (alternativa moderna)
            if (!$success) {
                Log::info('Intentando conversión con Browsershot');
                $success = $this->convertWithBrowsershot($html, $imagePath);
                if ($success) {
                    $methodUsed = 'browsershot';
                    Log::info('Conversión exitosa con Browsershot');
                }
            }

            // Método 4: Usar una imagen estática como fallback
            if (!$success) {
                Log::info('Usando fallback de imagen estática');
                $success = $this->createStaticImage($calculadora, $imagePath);
                if ($success) {
                    $methodUsed = 'static_fallback';
                    Log::info('Imagen estática creada exitosamente');
                }
            }

            // Limpiar archivo temporal
            unlink($tempHtmlPath);

            if ($success && file_exists($imagePath)) {
                Log::info('Imagen del resumen de costos generada exitosamente', [
                    'calculadora_id' => $calculadora->id,
                    'image_path' => $imagePath,
                    'method' => $methodUsed,
                    'file_size' => filesize($imagePath)
                ]);
                return $imagePath;
            }

            Log::error('No se pudo generar la imagen con ningún método', [
                'calculadora_id' => $calculadora->id,
                'chrome_available' => $chromeAvailable,
                'wkhtmltoimage_available' => $wkhtmltoimageAvailable
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error en convertHTMLToImage: ' . $e->getMessage(), [
                'calculadora_id' => $calculadora->id,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Verificar si Chrome está disponible
     */
    private function isChromeAvailable()
    {
        $chromePaths = [
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe'
        ];

        foreach ($chromePaths as $path) {
            if (file_exists($path)) {
                Log::info('Chrome encontrado en: ' . $path);
                return true;
            }
        }

        // Verificar si está en PATH
        $output = [];
        $returnCode = 0;
        exec('google-chrome --version', $output, $returnCode);
        if ($returnCode === 0) {
            Log::info('Chrome encontrado en PATH: ' . implode(' ', $output));
            return true;
        }

        exec('chromium-browser --version', $output, $returnCode);
        if ($returnCode === 0) {
            Log::info('Chromium encontrado en PATH: ' . implode(' ', $output));
            return true;
        }

        Log::warning('Chrome/Chromium no encontrado en el sistema');
        return false;
    }

    /**
     * Verificar si wkhtmltoimage está disponible
     */
    private function isWkhtmltoimageAvailable()
    {
        $output = [];
        $returnCode = 0;
        exec('wkhtmltoimage --version', $output, $returnCode);
        if ($returnCode === 0) {
            Log::info('wkhtmltoimage encontrado en PATH: ' . implode(' ', $output));
            return true;
        }

        // Verificar rutas comunes en Windows
        $wkhtmltoimagePaths = [
            'C:\Program Files\wkhtmltopdf\bin\wkhtmltoimage.exe',
            'C:\Program Files (x86)\wkhtmltopdf\bin\wkhtmltoimage.exe'
        ];

        foreach ($wkhtmltoimagePaths as $path) {
            if (file_exists($path)) {
                Log::info('wkhtmltoimage encontrado en: ' . $path);
                return true;
            }
        }

        Log::warning('wkhtmltoimage no encontrado en el sistema');
        return false;
    }

    /**
     * Convertir usando Chrome headless
     */
    private function convertWithChrome($htmlPath, $imagePath)
    {
        try {
            // Determinar el comando correcto según el sistema operativo
            $chromeCommand = 'google-chrome';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $chromeCommand = '"C:\Program Files\Google\Chrome\Application\chrome.exe"';
                if (!file_exists('C:\Program Files\Google\Chrome\Application\chrome.exe')) {
                    $chromeCommand = '"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"';
                }
            }

            // Comando para convertir HTML a imagen
            $command = $chromeCommand . ' --headless --disable-gpu --screenshot="' . $imagePath . '" --window-size=800,1200 "' . $htmlPath . '"';

            Log::info('Ejecutando comando Chrome: ' . $command);

            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            Log::info('Resultado del comando Chrome', [
                'return_code' => $returnCode,
                'output' => $output
            ]);

            if ($returnCode === 0 && file_exists($imagePath)) {
                Log::info('Chrome generó la imagen exitosamente');
                return true;
            }

            Log::warning('Chrome no pudo generar la imagen', [
                'return_code' => $returnCode,
                'output' => $output,
                'image_exists' => file_exists($imagePath)
            ]);

            return false;
        } catch (\Exception $e) {
            Log::warning('Error al usar Chrome headless: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Convertir usando wkhtmltoimage
     */
    private function convertWithWkhtmltoimage($htmlPath, $imagePath)
    {
        try {
            // Determinar el comando correcto según el sistema operativo
            $wkhtmltoimageCommand = 'wkhtmltoimage';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $wkhtmltoimageCommand = '"C:\Program Files\wkhtmltopdf\bin\wkhtmltoimage.exe"';
                if (!file_exists('C:\Program Files\wkhtmltopdf\bin\wkhtmltoimage.exe')) {
                    $wkhtmltoimageCommand = '"C:\Program Files (x86)\wkhtmltopdf\bin\wkhtmltoimage.exe"';
                }
            }

            $command = $wkhtmltoimageCommand . ' --width 800 --height 1200 --quality 100 --format png "' . $htmlPath . '" "' . $imagePath . '"';

            Log::info('Ejecutando comando wkhtmltoimage: ' . $command);

            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            Log::info('Resultado del comando wkhtmltoimage', [
                'return_code' => $returnCode,
                'output' => $output
            ]);

            if ($returnCode === 0 && file_exists($imagePath)) {
                Log::info('wkhtmltoimage generó la imagen exitosamente');
                return true;
            }

            Log::warning('wkhtmltoimage no pudo generar la imagen', [
                'return_code' => $returnCode,
                'output' => $output,
                'image_exists' => file_exists($imagePath)
            ]);

            return false;
        } catch (\Exception $e) {
            Log::warning('Error al usar wkhtmltoimage: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Convertir usando Browsershot
     */
    private function convertWithBrowsershot($html, $imagePath)
    {
        try {
            Log::info('Iniciando conversión con Browsershot');
            $browsershot = Browsershot::html($html)
                ->setOption('format', 'png')
                ->setOption('width', 800)
                ->setOption('height', 1200)
                ->setOption('quality', 100)
                ->setOption('disable-gpu', true)
                ->setOption('headless', true)
                ->setOption('window-size', '800,1200');

            $browsershot->save($imagePath);

            if (file_exists($imagePath)) {
                Log::info('Conversión exitosa con Browsershot');
                return true;
            }
            Log::warning('Browsershot no pudo generar la imagen', [
                'image_exists' => file_exists($imagePath)
            ]);
            return false;
        } catch (\Exception $e) {
            Log::warning('Error al usar Browsershot: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Crear imagen estática como fallback
     */
    private function createStaticImage($calculadora, $imagePath)
    {
        try {
            Log::info('Creando imagen estática');
            // Crear una imagen básica usando GD
            if (!extension_loaded('gd')) {
                return false;
            }

            $width = 800;
            $height = 1200;

            $image = imagecreatetruecolor($width, $height);

            // Colores
            $white = imagecolorallocate($image, 255, 255, 255);
            $darkBlue = imagecolorallocate($image, 52, 73, 94);
            $orange = imagecolorallocate($image, 230, 126, 34);
            $black = imagecolorallocate($image, 0, 0, 0);
            $gray = imagecolorallocate($image, 128, 128, 128);

            // Fondo blanco
            imagefill($image, 0, 0, $white);

            // Header
            imagefilledrectangle($image, 0, 0, $width, 80, $darkBlue);

            // Título
            $title = "RESUMEN DE COTIZACION DE IMPORTACION";
            $fontSize = 5;
            $titleWidth = imagefontwidth($fontSize) * strlen($title);
            $titleX = ($width - $titleWidth) / 2;
            imagestring($image, $fontSize, $titleX, 30, $title, $white);

            // Contenido básico
            $y = 120;
            $lineHeight = 30;

            // Sección de costos
            imagefilledrectangle($image, 20, $y, $width - 20, $y + 40, $darkBlue);
            imagestring($image, 4, 30, $y + 10, "RESUMEN DE COSTOS UNITARIOS", $white);
            $y += 60;

            // Tabla básica
            imagestring($image, 3, 30, $y, "Producto: " . ($calculadora->proveedores->first()->productos->first()->nombre ?? 'N/A'), $black);
            $y += $lineHeight;
            imagestring($image, 3, 30, $y, "Total FOB: $" . number_format($calculadora->total_fob ?? 0, 2), $black);
            $y += $lineHeight;
            imagestring($image, 3, 30, $y, "Total Impuestos: $" . number_format($calculadora->total_impuestos ?? 0, 2), $black);
            $y += $lineHeight;
            imagestring($image, 3, 30, $y, "Logistica: $" . number_format($calculadora->logistica ?? 0, 2), $black);
            $y += $lineHeight;

            // Total
            $total = ($calculadora->total_fob ?? 0) + ($calculadora->total_impuestos ?? 0) + ($calculadora->logistica ?? 0);
            imagefilledrectangle($image, 20, $y, $width - 20, $y + 60, $orange);
            imagestring($image, 5, 30, $y + 10, "INVERSION TOTAL: $" . number_format($total, 2), $white);

            // Guardar imagen
            $success = imagepng($image, $imagePath);
            imagedestroy($image);

            return $success;
        } catch (\Exception $e) {
            Log::warning('Error al crear imagen estática: ' . $e->getMessage());
            return false;
        }
    }
}
