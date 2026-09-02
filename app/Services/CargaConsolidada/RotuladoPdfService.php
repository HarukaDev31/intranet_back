<?php

namespace App\Services\CargaConsolidada;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generación del PDF de rotulado consolidado (plantilla Rotulado_Template.html).
 */
class RotuladoPdfService
{
    public const FONT_FAMILY = 'noto sans sc';

    public const FONT_RELATIVE_PATH = 'assets/fonts/NotoSansSC-Regular.otf';

    public const FONT_DOWNLOAD_URL = 'https://raw.githubusercontent.com/notofonts/noto-cjk/main/Sans/SubsetOTF/SC/NotoSansSC-Regular.otf';

    private const MIN_FONT_BYTES = 1000000;

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

        return $htmlContent;
    }

    /**
     * @param string $htmlContent UTF-8
     * @return string PDF binario
     */
    public function renderPdf($htmlContent)
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('chroot', public_path());
        $options->set('defaultFont', self::FONT_FAMILY);
        $options->set('defaultMediaType', 'print');

        $fontDir = public_path('assets/fonts');
        if (is_dir($fontDir)) {
            $options->set('fontDir', $fontDir);
            $options->set('fontCache', storage_path('fonts/dompdf'));
        }

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
     * @param Dompdf $dompdf
     */
    private function registerChineseFont(Dompdf $dompdf)
    {
        $fontPath = self::fontAbsolutePath();
        if (!is_file($fontPath)) {
            return;
        }

        $cacheDir = storage_path('fonts/dompdf');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        try {
            $dompdf->getFontMetrics()->registerFont(
                array(
                    'family' => self::FONT_FAMILY,
                    'style' => 'normal',
                    'weight' => 'normal',
                ),
                $fontPath
            );
        } catch (\Throwable $e) {
            // Si ya está registrada o falla el cache, DomPDF puede seguir con @font-face del HTML.
        }
    }
}
