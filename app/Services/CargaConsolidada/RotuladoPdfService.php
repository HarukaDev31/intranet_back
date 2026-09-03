<?php

namespace App\Services\CargaConsolidada;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;

/**
 * Generación del PDF de rotulado consolidado (plantilla Rotulado_Template.html).
 *
 * Usa el mismo enfoque estable de antes (DejaVu + Noto remoto via Google Fonts)
 * y embebe el header completo a resolución original.
 */
class RotuladoPdfService
{
    public const FONT_FAMILY = 'noto sans sc';

    public const FONT_RELATIVE_PATH = 'assets/fonts/NotoSansSC-Regular.ttf';

    public const FONT_DOWNLOAD_URL = 'https://cdn.jsdelivr.net/fontsource/fonts/noto-sans-sc@5.2.5/chinese-simplified-400-normal.ttf';

    private const HEADER_IMAGE_RELATIVE_PATH = 'assets/templates/ROTULADO_HEADER.png';

    private const ICONS_DIR = 'assets/templates/rotulado_icons';

    private const ICON_DISPLAY_SIZE = 16;

    private const FOOTER_ICON_SIZE = 22;

    private const MIN_FONT_BYTES = 1000000;

    /**
     * @var array<string, string>
     */
    private static $iconPlaceholders = array(
        '{{icon_company}}' => 'company.png',
        '{{icon_contact}}' => 'contact.png',
        '{{icon_location}}' => 'location.png',
        '{{icon_box}}' => 'box.png',
        '{{icon_qty}}' => 'qty.png',
        '{{icon_weight}}' => 'weight.png',
        '{{icon_measure}}' => 'measure.png',
        '{{icon_barcode}}' => 'barcode.png',
        '{{icon_peru_flag}}' => 'peru_flag.png',
        '{{icon_footer_box}}' => 'box.png',
    );

    /**
     * @param string $cliente
     * @param string $supplierCode
     * @param string|int $carga
     * @return string
     */
    public function buildHtml($cliente, $supplierCode, $carga)
    {
        $htmlFilePath = public_path('assets/templates/Rotulado_Template.html');
        if (!is_file($htmlFilePath)) {
            throw new \RuntimeException('No se encontró la plantilla de rotulado general');
        }

        $htmlContent = file_get_contents($htmlFilePath);
        if ($htmlContent === false) {
            throw new \RuntimeException('No se pudo leer la plantilla de rotulado');
        }

        $encoding = mb_detect_encoding($htmlContent, array('UTF-8', 'ISO-8859-1', 'Windows-1252'), true);
        if ($encoding && strtoupper($encoding) !== 'UTF-8') {
            $htmlContent = mb_convert_encoding($htmlContent, 'UTF-8', $encoding);
        }

        $htmlContent = str_replace('{{cliente}}', htmlspecialchars((string) $cliente, ENT_QUOTES, 'UTF-8'), $htmlContent);
        $htmlContent = str_replace('{{supplier_code}}', htmlspecialchars((string) $supplierCode, ENT_QUOTES, 'UTF-8'), $htmlContent);
        $htmlContent = str_replace('{{carga}}', htmlspecialchars((string) $carga, ENT_QUOTES, 'UTF-8'), $htmlContent);

        return $this->embedAssets($htmlContent);
    }

    /**
     * Mismo setup DomPDF que el job original (estable en Adobe/navegador).
     *
     * @param string $htmlContent UTF-8
     * @return string PDF binario
     */
    public function renderPdf($htmlContent)
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', false);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('chroot', public_path());
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($htmlContent, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @return string
     */
    public static function fontAbsolutePath()
    {
        return public_path(self::FONT_RELATIVE_PATH);
    }

    /**
     * @return bool
     */
    public static function fontInstalled()
    {
        $path = self::fontAbsolutePath();

        return is_file($path) && filesize($path) >= self::MIN_FONT_BYTES;
    }

    /**
     * @param string $htmlContent
     * @return string
     */
    private function embedAssets($htmlContent)
    {
        $htmlContent = $this->embedHeaderImage($htmlContent);

        foreach (self::$iconPlaceholders as $placeholder => $filename) {
            $htmlContent = str_replace($placeholder, $this->pngDataUri($filename), $htmlContent);
        }

        $htmlContent = str_replace('{{icon_display_size}}', (string) self::ICON_DISPLAY_SIZE, $htmlContent);
        $htmlContent = str_replace('{{footer_icon_size}}', (string) self::FOOTER_ICON_SIZE, $htmlContent);

        return $htmlContent;
    }

    /**
     * Header completo a resolución original (como antes).
     *
     * @param string $htmlContent
     * @return string
     */
    private function embedHeaderImage($htmlContent)
    {
        $headerImagePath = public_path(self::HEADER_IMAGE_RELATIVE_PATH);
        $headerImageSrc = '';

        if (is_file($headerImagePath)) {
            $imageData = file_get_contents($headerImagePath);
            if ($imageData !== false) {
                $headerImageSrc = 'data:image/png;base64,' . base64_encode($imageData);
            }
        } else {
            Log::warning('RotuladoPdfService: no se encontró ROTULADO_HEADER.png');
        }

        $htmlContent = str_replace('{{header_image}}', $headerImageSrc, $htmlContent);
        $htmlContent = str_replace('{{base_url}}/assets/templates/ROTULADO_HEADER.png', $headerImageSrc, $htmlContent);

        return $htmlContent;
    }

    /**
     * @param string $filename
     * @return string
     */
    private function pngDataUri($filename)
    {
        $path = public_path(self::ICONS_DIR . '/' . $filename);
        if (!is_file($path)) {
            Log::warning('RotuladoPdfService: icono no encontrado', array('file' => $filename));

            return '';
        }

        $data = file_get_contents($path);

        return $data !== false ? 'data:image/png;base64,' . base64_encode($data) : '';
    }
}
