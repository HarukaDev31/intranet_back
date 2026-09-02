<?php

namespace App\Services\CargaConsolidada;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;

/**
 * Generación del PDF de rotulado consolidado (plantilla Rotulado_Template.html).
 */
class RotuladoPdfService
{
    public const FONT_FAMILY = 'noto sans sc';

    public const FONT_RELATIVE_PATH = 'assets/fonts/NotoSansSC-Regular.otf';

    public const FONT_DOWNLOAD_URL = 'https://raw.githubusercontent.com/notofonts/noto-cjk/main/Sans/SubsetOTF/SC/NotoSansSC-Regular.otf';

    private const HEADER_IMAGE_RELATIVE_PATH = 'assets/templates/ROTULADO_HEADER.png';

    private const ICONS_DIR = 'assets/templates/rotulado_icons';

    private const ICON_DISPLAY_SIZE = 22;

    private const FOOTER_ICON_SIZE = 28;

    private const MIN_FONT_BYTES = 1000000;

    /** Altura máxima del banner (px). Ajustar si el PDF pasa a 2 páginas. */
    private const HEADER_MAX_HEIGHT = 160;

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
     * @param string $htmlContent UTF-8
     * @return string PDF binario
     */
    public function renderPdf($htmlContent)
    {
        $fontDir = storage_path('fonts');
        if (!is_dir($fontDir)) {
            @mkdir($fontDir, 0755, true);
        }

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('chroot', public_path());
        $options->set('fontDir', $fontDir);
        $options->set('fontCache', $fontDir);
        $options->set('defaultFont', self::FONT_FAMILY);
        $options->set('defaultMediaType', 'print');

        $dompdf = new Dompdf($options);
        $this->registerChineseFont($dompdf);

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

    /**
     * @param string $htmlContent
     * @return string
     */
    private function embedHeaderImage($htmlContent)
    {
        $headerImagePath = public_path(self::HEADER_IMAGE_RELATIVE_PATH);
        $headerImageSrc = '';
        $headerWidth = 515;
        $headerHeight = self::HEADER_MAX_HEIGHT;

        if (is_file($headerImagePath)) {
            $resized = $this->resizeHeaderPng($headerImagePath, self::HEADER_MAX_HEIGHT);
            if ($resized !== null) {
                $headerImageSrc = 'data:image/png;base64,' . base64_encode($resized['data']);
                $headerWidth = $resized['width'];
                $headerHeight = $resized['height'];
            }
        }

        $htmlContent = str_replace('{{header_image}}', $headerImageSrc, $htmlContent);
        $htmlContent = str_replace('{{header_image_width}}', (string) $headerWidth, $htmlContent);
        $htmlContent = str_replace('{{header_image_height}}', (string) $headerHeight, $htmlContent);
        $htmlContent = str_replace('{{icon_display_size}}', (string) self::ICON_DISPLAY_SIZE, $htmlContent);
        $htmlContent = str_replace('{{footer_icon_size}}', (string) self::FOOTER_ICON_SIZE, $htmlContent);
        $htmlContent = str_replace('{{base_url}}/assets/templates/ROTULADO_HEADER.png', $headerImageSrc, $htmlContent);

        return $htmlContent;
    }

    /**
     * @param string $path
     * @param int $maxHeight
     * @return array{data: string, width: int, height: int}|null
     */
    private function resizeHeaderPng($path, $maxHeight)
    {
        if (!function_exists('imagecreatefrompng')) {
            $data = file_get_contents($path);

            return $data !== false
                ? array('data' => $data, 'width' => 515, 'height' => $maxHeight)
                : null;
        }

        $img = @imagecreatefrompng($path);
        if ($img === false) {
            return null;
        }

        $origW = imagesx($img);
        $origH = imagesy($img);
        $newH = min($maxHeight, $origH);
        $newW = (int) round($origW * ($newH / $origH));
        $resized = imagecreatetruecolor($newW, $newH);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($img);

        ob_start();
        imagepng($resized);
        $data = ob_get_clean();
        imagedestroy($resized);

        if ($data === false) {
            return null;
        }

        return array(
            'data' => $data,
            'width' => $newW,
            'height' => $newH,
        );
    }

    /**
     * @param Dompdf $dompdf
     */
    private function registerChineseFont(Dompdf $dompdf)
    {
        $fontPath = self::fontAbsolutePath();
        if (!is_file($fontPath)) {
            Log::warning('RotuladoPdfService: fuente Noto Sans SC no instalada. Ejecute php artisan rotulado:download-font');

            return;
        }

        try {
            $registered = $dompdf->getFontMetrics()->registerFont(
                array(
                    'family' => self::FONT_FAMILY,
                    'style' => 'normal',
                    'weight' => 'normal',
                ),
                $fontPath
            );

            if (!$registered) {
                Log::warning('RotuladoPdfService: no se pudo registrar la fuente china en DomPDF', array(
                    'path' => $fontPath,
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('RotuladoPdfService: error registrando fuente china: ' . $e->getMessage());
        }
    }
}
