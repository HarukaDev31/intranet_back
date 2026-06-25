<?php

namespace App\Support\WhatsApp;

/**
 * Recomprime/redimensiona imágenes que superan el tope de WhatsApp (5 MB) antes de subir a S3.
 * Prioriza Imagick (disponible en producción); si no, usa GD. En entornos sin ninguno
 * (p. ej. local), no falla: deja el archivo original y avisa por log.
 */
class WaInboxImageCompressor
{
    /**
     * @param  string  $inputPath  Ruta absoluta al archivo subido
     * @return array{success: bool, path?: string, mime?: string, recompressed?: bool, error?: string}
     */
    public function compressForWhatsApp($inputPath)
    {
        if (!is_file($inputPath)) {
            return ['success' => false, 'error' => 'Archivo de imagen no encontrado'];
        }

        if (!config('meta_whatsapp.image_compress_enabled', true)) {
            return ['success' => true, 'path' => $inputPath, 'recompressed' => false];
        }

        $targetBytes = (int) config('meta_whatsapp.inbox_image_compress_target_bytes', 4 * 1024 * 1024 + 512 * 1024);
        if ($targetBytes <= 0) {
            $targetBytes = 4 * 1024 * 1024 + 512 * 1024;
        }

        $size = (int) @filesize($inputPath);
        if ($size > 0 && $size <= $targetBytes) {
            return ['success' => true, 'path' => $inputPath, 'recompressed' => false];
        }

        $maxDimension = max(640, (int) config('meta_whatsapp.inbox_image_compress_max_dimension', 2560));
        $outputPath = $this->buildOutputPath($inputPath);

        if (extension_loaded('imagick') && class_exists('Imagick')) {
            $result = $this->compressWithImagick($inputPath, $outputPath, $targetBytes, $maxDimension);
            if (!empty($result['success'])) {
                return $result;
            }
            WaInboxLog::warning('imageCompress.imagick_failed', [
                'error' => isset($result['error']) ? (string) $result['error'] : null,
            ]);
        }

        if (function_exists('imagecreatetruecolor')) {
            $result = $this->compressWithGd($inputPath, $outputPath, $targetBytes, $maxDimension);
            if (!empty($result['success'])) {
                return $result;
            }
            WaInboxLog::warning('imageCompress.gd_failed', [
                'error' => isset($result['error']) ? (string) $result['error'] : null,
            ]);
        }

        WaInboxLog::warning('imageCompress.no_backend', [
            'input_bytes' => $size,
            'target_bytes' => $targetBytes,
            'imagick' => extension_loaded('imagick'),
            'gd' => function_exists('imagecreatetruecolor'),
        ]);

        return [
            'success' => false,
            'error' => 'La imagen pesa ' . round($size / 1024 / 1024, 1)
                . ' MB y supera el máximo de WhatsApp (5 MB). Este servidor no tiene Imagick/GD para comprimirla; '
                . 'reduce el tamaño de la imagen e inténtalo de nuevo.',
        ];
    }

    /**
     * @param  string  $inputPath
     * @param  string  $outputPath
     * @param  int  $targetBytes
     * @param  int  $maxDimension
     * @return array{success: bool, path?: string, mime?: string, recompressed?: bool, error?: string}
     */
    private function compressWithImagick($inputPath, $outputPath, $targetBytes, $maxDimension)
    {
        try {
            $img = new \Imagick($inputPath);

            if ($img->getNumberImages() > 1) {
                $img = $img->coalesceImages();
                foreach ($img as $frame) {
                    $frame->setImageFormat('jpeg');
                }
                $img = $img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            }

            $img->setImageFormat('jpeg');
            $img->setImageBackgroundColor('white');
            if (method_exists($img, 'setImageAlphaChannel')) {
                $img->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            }
            $img = $img->flattenImages();
            $img->stripImage();

            $this->imagickResizeToMaxDimension($img, $maxDimension);

            $quality = 88;
            for ($attempt = 0; $attempt < 9; $attempt++) {
                $img->setImageCompression(\Imagick::COMPRESSION_JPEG);
                $img->setImageCompressionQuality($quality);
                $img->writeImage($outputPath);

                $outSize = (int) @filesize($outputPath);
                if ($outSize > 0 && $outSize <= $targetBytes) {
                    $img->clear();
                    $img->destroy();

                    WaInboxLog::info('imageCompress.imagick_ok', [
                        'input_bytes' => (int) @filesize($inputPath),
                        'output_bytes' => $outSize,
                        'quality' => $quality,
                    ]);

                    return [
                        'success' => true,
                        'path' => $outputPath,
                        'mime' => 'image/jpeg',
                        'recompressed' => true,
                    ];
                }

                if ($quality > 45) {
                    $quality -= 8;
                } else {
                    $w = (int) $img->getImageWidth();
                    $h = (int) $img->getImageHeight();
                    $newW = (int) max(640, floor($w * 0.85));
                    $newH = (int) max(1, floor($h * ($newW / max(1, $w))));
                    $img->resizeImage($newW, $newH, \Imagick::FILTER_LANCZOS, 1);
                }
            }

            $img->clear();
            $img->destroy();
            @unlink($outputPath);

            return ['success' => false, 'error' => 'No se alcanzó el tamaño objetivo con Imagick'];
        } catch (\Throwable $e) {
            @unlink($outputPath);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  \Imagick  $img
     * @param  int  $maxDimension
     * @return void
     */
    private function imagickResizeToMaxDimension($img, $maxDimension)
    {
        $w = (int) $img->getImageWidth();
        $h = (int) $img->getImageHeight();
        $longest = max($w, $h);
        if ($longest <= $maxDimension || $longest <= 0) {
            return;
        }

        $ratio = $maxDimension / $longest;
        $img->resizeImage(
            (int) max(1, floor($w * $ratio)),
            (int) max(1, floor($h * $ratio)),
            \Imagick::FILTER_LANCZOS,
            1
        );
    }

    /**
     * @param  string  $inputPath
     * @param  string  $outputPath
     * @param  int  $targetBytes
     * @param  int  $maxDimension
     * @return array{success: bool, path?: string, mime?: string, recompressed?: bool, error?: string}
     */
    private function compressWithGd($inputPath, $outputPath, $targetBytes, $maxDimension)
    {
        try {
            $info = @getimagesize($inputPath);
            if ($info === false) {
                return ['success' => false, 'error' => 'Imagen no legible por GD'];
            }

            $type = (int) $info[2];
            $src = $this->gdCreateFromType($inputPath, $type);
            if ($src === null) {
                return ['success' => false, 'error' => 'Formato de imagen no soportado por GD'];
            }

            $src = $this->gdResizeToMaxDimension($src, $maxDimension);

            $quality = 88;
            for ($attempt = 0; $attempt < 9; $attempt++) {
                if (!@imagejpeg($src, $outputPath, $quality)) {
                    imagedestroy($src);
                    @unlink($outputPath);

                    return ['success' => false, 'error' => 'GD no pudo escribir la imagen'];
                }

                $outSize = (int) @filesize($outputPath);
                if ($outSize > 0 && $outSize <= $targetBytes) {
                    imagedestroy($src);

                    WaInboxLog::info('imageCompress.gd_ok', [
                        'input_bytes' => (int) @filesize($inputPath),
                        'output_bytes' => $outSize,
                        'quality' => $quality,
                    ]);

                    return [
                        'success' => true,
                        'path' => $outputPath,
                        'mime' => 'image/jpeg',
                        'recompressed' => true,
                    ];
                }

                if ($quality > 45) {
                    $quality -= 8;
                } else {
                    $resized = $this->gdScaleDown($src, 0.85);
                    if ($resized === null) {
                        break;
                    }
                    imagedestroy($src);
                    $src = $resized;
                }
            }

            imagedestroy($src);
            @unlink($outputPath);

            return ['success' => false, 'error' => 'No se alcanzó el tamaño objetivo con GD'];
        } catch (\Throwable $e) {
            @unlink($outputPath);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  string  $inputPath
     * @param  int  $type
     * @return \GdImage|resource|null
     */
    private function gdCreateFromType($inputPath, $type)
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                $img = @imagecreatefromjpeg($inputPath);
                break;
            case IMAGETYPE_PNG:
                $img = @imagecreatefrompng($inputPath);
                break;
            case IMAGETYPE_GIF:
                $img = @imagecreatefromgif($inputPath);
                break;
            case IMAGETYPE_WEBP:
                $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($inputPath) : false;
                break;
            case IMAGETYPE_BMP:
                $img = function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($inputPath) : false;
                break;
            default:
                $img = false;
        }

        if (!$img) {
            return null;
        }

        return $this->gdFlattenOnWhite($img);
    }

    /**
     * Aplana transparencia sobre blanco para que el JPEG no salga con fondo negro.
     *
     * @param  \GdImage|resource  $img
     * @return \GdImage|resource
     */
    private function gdFlattenOnWhite($img)
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $canvas = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $w, $h, $white);
        imagecopy($canvas, $img, 0, 0, 0, 0, $w, $h);
        imagedestroy($img);

        return $canvas;
    }

    /**
     * @param  \GdImage|resource  $img
     * @param  int  $maxDimension
     * @return \GdImage|resource
     */
    private function gdResizeToMaxDimension($img, $maxDimension)
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $longest = max($w, $h);
        if ($longest <= $maxDimension || $longest <= 0) {
            return $img;
        }

        $ratio = $maxDimension / $longest;
        $resized = $this->gdScaleDown($img, $ratio);
        if ($resized === null) {
            return $img;
        }
        imagedestroy($img);

        return $resized;
    }

    /**
     * @param  \GdImage|resource  $img
     * @param  float  $ratio
     * @return \GdImage|resource|null
     */
    private function gdScaleDown($img, $ratio)
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $newW = (int) max(1, floor($w * $ratio));
        $newH = (int) max(1, floor($h * $ratio));
        if ($newW >= $w && $newH >= $h) {
            return null;
        }

        $dst = imagecreatetruecolor($newW, $newH);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);
        if (!imagecopyresampled($dst, $img, 0, 0, 0, 0, $newW, $newH, $w, $h)) {
            imagedestroy($dst);

            return null;
        }

        return $dst;
    }

    /**
     * @param  string  $inputPath
     * @return string
     */
    private function buildOutputPath($inputPath)
    {
        $dir = dirname($inputPath);

        return $dir . DIRECTORY_SEPARATOR . 'wa_img_' . uniqid('', true) . '.jpg';
    }
}
