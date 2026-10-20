<?php

namespace App\Services\CargaConsolidada;

use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Services\Organizacion\OrganizacionMensajeriaService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;

/**
     * Generación del PDF de rotulado consolidado.
     * Las orgs 1, 2 y 3 usan Rotulado_Template_Org1.html; el resto, Rotulado_Template.html.
 *
 * Usa el mismo enfoque estable de antes (DejaVu + Noto remoto via Google Fonts)
 * y embebe el header completo a resolución original.
 */
class RotuladoPdfService
{
    public const FONT_FAMILY = 'noto sans sc';

    public const FONT_RELATIVE_PATH = 'assets/fonts/NotoSansSC-Regular.ttf';

    /**
     * Copia versionada. public/assets/fonts/*.ttf está en .gitignore.
     */
    public const FONT_CACHE_FILE = 'noto_sans_sc_normal_14952c46f80d9c9df18834d87b82870c.ttf';

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
     * @param array<string, mixed> $opts company, pais_destino, iso2, organizacion_id
     * @return string
     */
    public function buildHtml($cliente, $supplierCode, $carga, array $opts = array())
    {
        $orgId = isset($opts['organizacion_id']) ? (int) $opts['organizacion_id'] : 0;
        $tema = $this->temaTarjeta($orgId);
        $htmlFilePath = public_path($tema !== null
            ? 'assets/templates/Rotulado_Template_Org1.html'
            : 'assets/templates/Rotulado_Template.html');
        if (!is_file($htmlFilePath)) {
            throw new \RuntimeException('No se encontró la plantilla de rotulado general');
        }

        $htmlContent = file_get_contents($htmlFilePath);
        if ($htmlContent === false) {
            throw new \RuntimeException('No se pudo leer la plantilla de rotulado');
        }

        if (self::fontInstalled()) {
            $htmlContent = preg_replace('#<link[^>]+fonts\.(googleapis|gstatic)\.com[^>]*>#i', '', $htmlContent);
        }

        $encoding = mb_detect_encoding($htmlContent, array('UTF-8', 'ISO-8859-1', 'Windows-1252'), true);
        if ($encoding && strtoupper($encoding) !== 'UTF-8') {
            $htmlContent = mb_convert_encoding($htmlContent, 'UTF-8', $encoding);
        }

        $company = isset($opts['company']) && trim((string) $opts['company']) !== ''
            ? (string) $opts['company']
            : 'PRO MUNDO COMEX S.A.C';
        $paisDestino = isset($opts['pais_destino']) && trim((string) $opts['pais_destino']) !== ''
            ? strtoupper(trim((string) $opts['pais_destino']))
            : 'PERU';
        $iso2 = isset($opts['iso2']) ? strtolower(trim((string) $opts['iso2'])) : 'pe';
        $iso2 = strtolower(preg_replace('/[^a-z]/', '', $iso2));
        if (strlen($iso2) !== 2) {
            $iso2 = 'pe';
        }
        $paisBanner = $iso2 === 'pe' ? 'PERÚ' : $paisDestino;
        $marca = strtoupper($iso2);
        $marcaSize = '36pt';
        $socio = 'PROBUSINESS';
        $sheetColor = '#E10613';
        if ($tema !== null) {
            $sheetColor = $tema['color'];
            $marca = $tema['marca'] !== '' ? $tema['marca'] : $marca;
            $marcaSize = $tema['marca_size'];
            $socio = $tema['socio'];
            if ($tema['iso2'] !== '') {
                $iso2 = $tema['iso2'];
                $paisBanner = $tema['banner'];
                $paisDestino = $tema['banner'];
            }
        }

        $htmlContent = str_replace('{{cliente}}', htmlspecialchars((string) $cliente, ENT_QUOTES, 'UTF-8'), $htmlContent);
        $htmlContent = str_replace('{{supplier_code}}', htmlspecialchars((string) $supplierCode, ENT_QUOTES, 'UTF-8'), $htmlContent);
        $htmlContent = str_replace('{{carga}}', htmlspecialchars((string) $carga, ENT_QUOTES, 'UTF-8'), $htmlContent);
        $htmlContent = str_replace('{{company}}', htmlspecialchars($company, ENT_QUOTES, 'UTF-8'), $htmlContent);
        $htmlContent = str_replace('{{pais_destino}}', htmlspecialchars($paisDestino, ENT_QUOTES, 'UTF-8'), $htmlContent);
        $htmlContent = str_replace('{{iso_code}}', htmlspecialchars(strtoupper($iso2), ENT_QUOTES, 'UTF-8'), $htmlContent);
        $htmlContent = str_replace('{{marca}}', htmlspecialchars($marca, ENT_QUOTES, 'UTF-8'), $htmlContent);
        $htmlContent = str_replace('{{marca_size}}', $marcaSize, $htmlContent);
        $htmlContent = str_replace('{{sheet_color}}', $sheetColor, $htmlContent);
        $htmlContent = str_replace('{{pais_banner}}', htmlspecialchars($paisBanner, ENT_QUOTES, 'UTF-8'), $htmlContent);
        $htmlContent = str_replace('{{pais_zh}}', htmlspecialchars($this->paisZh($iso2), ENT_QUOTES, 'UTF-8'), $htmlContent);
        $htmlContent = str_replace('{{socio_nombre}}', htmlspecialchars($socio, ENT_QUOTES, 'UTF-8'), $htmlContent);
        $htmlContent = str_replace('{{noto_face}}', $this->notoFaceCss(), $htmlContent);

        return $this->embedAssets($htmlContent, $iso2, $orgId);
    }

    /**
     * PDF de rotulado usando país del contenedor (socios) y company de la org.
     *
     * @param string $cliente
     * @param string $supplierCode
     * @param string|int $carga
     * @param Cotizacion|null $cotizacion
     * @return string
     */
    public function buildHtmlForCotizacion($cliente, $supplierCode, $carga, $cotizacion = null)
    {
        return $this->buildHtml($cliente, $supplierCode, $carga, $this->optionsFromCotizacion($cotizacion));
    }

    /**
     * @param Cotizacion|null $cotizacion
     * @return array<string, mixed>
     */
    public function optionsFromCotizacion($cotizacion)
    {
        $opts = array(
            'company' => 'PRO MUNDO COMEX S.A.C',
            'pais_destino' => 'PERU',
            'iso2' => 'pe',
            'organizacion_id' => OrganizacionMensajeriaService::ID_ORGANIZACION_ADMIN,
        );
        if (!$cotizacion) {
            return $opts;
        }

        $orgId = (int) $cotizacion->getAttribute('organizacion_id');
        $opts['organizacion_id'] = $orgId > 0 ? $orgId : OrganizacionMensajeriaService::ID_ORGANIZACION_ADMIN;

        $mensajeria = app(OrganizacionMensajeriaService::class);
        if ($opts['organizacion_id'] !== OrganizacionMensajeriaService::ID_ORGANIZACION_ADMIN) {
            $opts['company'] = $mensajeria->companyRotulado($opts['organizacion_id']);
        }

        $idContenedor = (int) $cotizacion->getAttribute('id_contenedor');
        $contenedor = $idContenedor > 0 ? Contenedor::query()->find($idContenedor) : null;
        $pais = $mensajeria->paisDesdeContenedor($contenedor);
        if ($opts['organizacion_id'] !== OrganizacionMensajeriaService::ID_ORGANIZACION_ADMIN) {
            if ($pais['nombre'] !== '') {
                $opts['pais_destino'] = $pais['nombre'];
            }
            if ($pais['iso2'] !== '') {
                $opts['iso2'] = $pais['iso2'];
            }
        }

        return $opts;
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
        $options->set('chroot', array(public_path(), storage_path('fonts')));
        $options->set('fontDir', storage_path('fonts'));
        $options->set('fontCache', storage_path('fonts'));
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $this->registerNoto($dompdf);
        $dompdf->loadHtml($htmlContent, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Noto Sans SC en peso normal y bold apuntando al mismo TTF.
     * DomPDF, si pide negrita y no hay archivo bold, cambia a DejaVu y el chino sale en blanco.
     *
     * @return string
     */
    private function notoFaceCss()
    {
        if (!self::fontInstalled() || self::usesCommittedFont()) {
            return '';
        }

        $src = "src:url('" . self::fontFileUrl() . "') format('truetype');";

        return "@font-face{font-family:'Noto Sans SC';font-style:normal;font-weight:400;{$src}}"
            . "@font-face{font-family:'Noto Sans SC';font-style:normal;font-weight:700;{$src}}";
    }

    /**
     * @param Dompdf $dompdf
     * @return void
     */
    private function registerNoto(Dompdf $dompdf)
    {
        if (!self::fontInstalled() || self::usesCommittedFont()) {
            return;
        }

        $url = self::fontFileUrl();
        $metrics = $dompdf->getFontMetrics();
        foreach (array('normal', 'bold') as $weight) {
            $metrics->registerFont(
                array('family' => 'Noto Sans SC', 'weight' => $weight, 'style' => 'normal'),
                $url
            );
        }
    }

    /**
     * file://C:/... en Windows. file:///C: hace que DomPDF tome "C" como host.
     *
     * @return string
     */
    public static function fontFileUrl()
    {
        return 'file://' . str_replace('\\', '/', self::fontAbsolutePath());
    }

    /**
     * @return string
     */
    public static function fontAbsolutePath()
    {
        $public = public_path(self::FONT_RELATIVE_PATH);
        if (self::fontFileOk($public)) {
            return $public;
        }

        $committed = storage_path('fonts/' . self::FONT_CACHE_FILE);
        if (self::fontFileOk($committed)) {
            return $committed;
        }

        return $public;
    }

    /**
     * La copia de storage/fonts ya está en installed-fonts.json.
     *
     * @return bool
     */
    private static function usesCommittedFont()
    {
        $path = self::fontAbsolutePath();

        return self::fontFileOk($path)
            && basename($path) === self::FONT_CACHE_FILE;
    }

    /**
     * @param string $path
     * @return bool
     */
    private static function fontFileOk($path)
    {
        return is_file($path) && filesize($path) >= self::MIN_FONT_BYTES;
    }

    /**
     * @return bool
     */
    public static function fontInstalled()
    {
        return self::fontFileOk(self::fontAbsolutePath());
    }

    /**
     * @param string $htmlContent
     * @param string $iso2
     * @return string
     */
    private function embedAssets($htmlContent, $iso2 = 'pe', $orgId = 0)
    {
        $htmlContent = $this->embedHeaderImage($htmlContent, $orgId);

        foreach (self::$iconPlaceholders as $placeholder => $filename) {
            $htmlContent = str_replace($placeholder, $this->pngDataUri($filename), $htmlContent);
        }

        $flagUri = $this->flagDataUri($iso2);
        $htmlContent = str_replace('{{icon_pais_flag}}', $flagUri, $htmlContent);
        $htmlContent = str_replace('{{icon_peru_flag}}', $flagUri, $htmlContent);

        $htmlContent = str_replace('{{icon_display_size}}', (string) self::ICON_DISPLAY_SIZE, $htmlContent);
        $htmlContent = str_replace('{{footer_icon_size}}', (string) self::FOOTER_ICON_SIZE, $htmlContent);

        return $htmlContent;
    }

    /**
     * Header completo a resolución original (como antes).
     *
     * Usa la imagen "Pasos 1 y 2 (foto)" de la organización (panel-acceso/organizaciones)
     * como cabecera; sin imagen propia queda la cabecera de plantilla.
     *
     * @param string $htmlContent
     * @param int $orgId
     * @return string
     */
    private function embedHeaderImage($htmlContent, $orgId = 0)
    {
        $headerImageSrc = $this->orgHeaderDataUri($orgId);

        if ($headerImageSrc === '') {
            $headerImagePath = public_path(self::HEADER_IMAGE_RELATIVE_PATH);
            if (is_file($headerImagePath)) {
                $imageData = file_get_contents($headerImagePath);
                if ($imageData !== false) {
                    $headerImageSrc = 'data:image/png;base64,' . base64_encode($imageData);
                }
            } else {
                Log::warning('RotuladoPdfService: no se encontró ROTULADO_HEADER.png');
            }
        }

        $size = $this->headerCssSize($headerImageSrc);

        $htmlContent = str_replace('{{header_image}}', $headerImageSrc, $htmlContent);
        $htmlContent = str_replace('{{header_width}}', (string) $size[0], $htmlContent);
        $htmlContent = str_replace('{{header_height}}', (string) $size[1], $htmlContent);
        $htmlContent = str_replace('{{base_url}}/assets/templates/ROTULADO_HEADER.png', $headerImageSrc, $htmlContent);

        return $htmlContent;
    }

    /**
     * Escala la cabecera para que, junto con la etiqueta, quepa en una hoja A4.
     * Los atributos width/height de DomPDF van en pixeles CSS (96 dpi).
     *
     * @param string $dataUri
     * @return array{0:int,1:int}
     */
    private function headerCssSize($dataUri)
    {
        $maxW = 860;
        $maxH = 635;
        $pxW = 642;
        $pxH = 627;
        $comma = strpos((string) $dataUri, 'base64,');
        if ($comma !== false) {
            $binary = base64_decode(substr($dataUri, $comma + 7), true);
            if ($binary !== false) {
                $info = @getimagesizefromstring($binary);
                if (is_array($info) && $info[0] > 0 && $info[1] > 0) {
                    $pxW = (int) $info[0];
                    $pxH = (int) $info[1];
                }
            }
        }
        $scale = min($maxW / $pxW, $maxH / $pxH);

        return array((int) round($pxW * $scale), (int) round($pxH * $scale));
    }

    /**
     * Imagen de cabecera subida por la organización (slot paso1). '' si no hay o no es un formato que DomPDF soporte.
     *
     * @param int $orgId
     * @return string
     */
    private function orgHeaderDataUri($orgId)
    {
        $orgId = (int) $orgId;
        if ($orgId <= 0) {
            return '';
        }

        try {
            $path = app(OrganizacionMensajeriaService::class)->localPathImagen($orgId, OrganizacionMensajeriaService::IMG_PASO1);
            if (!$path || !is_file($path)) {
                Log::info('[ROTULADO_HEADER] la organizacion no tiene imagen de cabecera (o no se pudo leer): se usa la de plantilla', [
                    'organizacion_id' => $orgId,
                    'path' => $path,
                ]);

                return '';
            }
            $info = @getimagesize($path);
            $mime = is_array($info) ? (string) $info['mime'] : '';
            $data = file_get_contents($path);
            if ($data === false) {
                return '';
            }

            if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
                $img = @imagecreatefromstring($data);
                if ($img !== false) {
                    ob_start();
                    imagepng($img);
                    $png = (string) ob_get_clean();
                    imagedestroy($img);
                    if ($png !== '') {
                        Log::info('[ROTULADO_HEADER] cabecera WebP convertida a PNG', ['organizacion_id' => $orgId]);

                        return 'data:image/png;base64,' . base64_encode($png);
                    }
                }
            }

            if (!in_array($mime, array('image/png', 'image/jpeg', 'image/gif'), true)) {
                Log::warning('[ROTULADO_HEADER] formato de cabecera no soportado, se usa la plantilla', array(
                    'organizacion_id' => $orgId,
                    'mime' => $mime,
                    'path' => $path,
                ));

                return '';
            }

            Log::info('[ROTULADO_HEADER] usando la cabecera de la organizacion', array(
                'organizacion_id' => $orgId,
                'mime' => $mime,
                'bytes' => strlen($data),
            ));

            return 'data:' . $mime . ';base64,' . base64_encode($data);
        } catch (\Throwable $e) {
            Log::warning('[ROTULADO_HEADER] no se pudo cargar la cabecera de la organizacion', array(
                'organizacion_id' => $orgId,
                'error' => $e->getMessage(),
            ));

            return '';
        }
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
     * Nombre del país en chino para la franja del rótulo de la org 1.
     *
     * @param string $iso2
     * @return string
     */
    private function paisZh($iso2)
    {
        $nombres = array(
            'pe' => '秘鲁',
            'bo' => '玻利维亚',
            'cl' => '智利',
            'ec' => '厄瓜多尔',
            'co' => '哥伦比亚',
            'mx' => '墨西哥',
            'ar' => '阿根廷',
            'br' => '巴西',
            'uy' => '乌拉圭',
            'py' => '巴拉圭',
        );

        return isset($nombres[$iso2]) ? $nombres[$iso2] : '';
    }

    /**
     * Tarjeta del paso 3. Org 1 roja, org 2 azul (Ecuador / David), org 3 verde (Bolivia / Genuino).
     * null = plantilla anterior, para el resto de socios.
     *
     * @param int $orgId
     * @return array{color:string,marca:string,marca_size:string,socio:string,iso2:string,banner:string}|null
     */
    private function temaTarjeta($orgId)
    {
        if ($orgId <= 0 || $orgId === OrganizacionMensajeriaService::ID_ORGANIZACION_ADMIN) {
            return array(
                'color' => '#E10613',
                'marca' => '',
                'marca_size' => '50pt',
                'socio' => 'PROBUSINESS',
                'iso2' => '',
                'banner' => '',
            );
        }
        if ($orgId === 2) {
            return array(
                'color' => '#0455C3',
                'marca' => 'ECU-DAV',
                'marca_size' => '32pt',
                'socio' => 'DAVID',
                'iso2' => 'ec',
                'banner' => 'ECUADOR',
            );
        }
        if ($orgId === 3) {
            return array(
                'color' => '#00AA4E',
                'marca' => 'BOG',
                'marca_size' => '50pt',
                'socio' => 'GENUINO',
                'iso2' => 'bo',
                'banner' => 'BOLIVIA',
            );
        }

        return null;
    }

    /**
     * Bandera PNG (flagcdn) embebida. Fallback a peru_flag.png.
     *
     * @param string $iso2
     * @return string
     */
    private function flagDataUri($iso2)
    {
        $iso2 = strtolower(preg_replace('/[^a-z]/', '', (string) $iso2));
        if (strlen($iso2) !== 2) {
            $iso2 = 'pe';
        }
        if ($iso2 === 'pe') {
            return $this->pngDataUri('peru_flag.png');
        }
        $locales = array(
            'ec' => 'ecuador_flag.png',
            'bo' => 'bolivia_flag.png',
        );
        if (isset($locales[$iso2])) {
            $local = $this->pngDataUri($locales[$iso2]);
            if ($local !== '') {
                return $local;
            }
        }

        $cacheDir = storage_path('app/rotulado_flags');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $cachePath = $cacheDir . '/' . $iso2 . '.png';
        if (!is_file($cachePath) || filesize($cachePath) < 100) {
            $url = 'https://flagcdn.com/w80/' . $iso2 . '.png';
            $ctx = stream_context_create(array(
                'http' => array('timeout' => 8, 'ignore_errors' => true),
                'ssl' => array('verify_peer' => true, 'verify_peer_name' => true),
            ));
            $bin = @file_get_contents($url, false, $ctx);
            if (is_string($bin) && strlen($bin) > 100) {
                @file_put_contents($cachePath, $bin);
            }
        }
        if (is_file($cachePath)) {
            $data = file_get_contents($cachePath);
            if ($data !== false) {
                return 'data:image/png;base64,' . base64_encode($data);
            }
        }

        Log::warning('RotuladoPdfService: no se pudo obtener bandera', array('iso2' => $iso2));

        return $this->pngDataUri('peru_flag.png');
    }
}
