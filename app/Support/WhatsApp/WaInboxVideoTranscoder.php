<?php

namespace App\Support\WhatsApp;

/**
 * Convierte videos de encabezado de plantilla a MP4 H.264 + AAC (requisito Meta WhatsApp).
 * Requiere binario ffmpeg en el servidor.
 */
class WaInboxVideoTranscoder
{
    /**
     * @param  string  $inputPath  Ruta absoluta al archivo subido
     * @return array{success: bool, path?: string, error?: string}
     */
    public function transcodeForWhatsAppTemplate($inputPath)
    {
        if (!is_file($inputPath)) {
            return ['success' => false, 'error' => 'Archivo de video no encontrado'];
        }

        if (!config('meta_whatsapp.video_transcode_enabled', true)) {
            return ['success' => false, 'error' => 'Conversión de video deshabilitada en el servidor'];
        }

        $ffmpeg = $this->resolveFfmpegBinary();
        if ($ffmpeg === null) {
            return [
                'success' => false,
                'error' => 'El servidor no tiene ffmpeg. Pide a sistemas instalarlo o convierte el video manualmente.',
            ];
        }

        $outputPath = $this->buildOutputPath($inputPath);
        $timeoutSec = max(30, (int) config('meta_whatsapp.video_transcode_timeout', 120));
        $maxBytes = (int) config('meta_whatsapp.inbox_header_max_bytes.video', 16 * 1024 * 1024);

        // Sin comas en el filtro: en filtergraph la coma separa filtros y rompe min(1280,iw) → "No such filter: iw):-2".
        $scaleFilter = 'scale=1280:-2:force_original_aspect_ratio=decrease';

        $cmd = $ffmpeg
            . ' -y -i ' . escapeshellarg($inputPath)
            . ' -c:v libx264 -profile:v main -pix_fmt yuv420p'
            . ' -vf ' . escapeshellarg($scaleFilter)
            . ' -c:a aac -b:a 128k -ac 1'
            . ' -movflags +faststart'
            . ' ' . escapeshellarg($outputPath)
            . ' 2>&1';

        WaInboxLog::info('videoTranscode.start', [
            'input' => basename($inputPath),
            'output' => basename($outputPath),
            'timeout_sec' => $timeoutSec,
        ]);

        $output = [];
        $exitCode = 1;
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['success' => false, 'error' => 'No se pudo ejecutar ffmpeg en el servidor'];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $started = time();
        $stdout = '';
        $stderr = '';

        while (true) {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exitCode = (int) $status['exitcode'];
                break;
            }
            if (time() - $started > $timeoutSec) {
                proc_terminate($process, 9);
                proc_close($process);
                @unlink($outputPath);

                return ['success' => false, 'error' => 'La conversión del video tardó demasiado. Prueba un archivo más corto o más pequeño.'];
            }
            usleep(200000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $log = trim($stdout . "\n" . $stderr);
        if ($exitCode !== 0 || !is_file($outputPath)) {
            WaInboxLog::error('videoTranscode.failed', [
                'exit_code' => $exitCode,
                'log_tail' => substr($log, -800),
            ]);
            @unlink($outputPath);

            return [
                'success' => false,
                'error' => 'No se pudo convertir el video para WhatsApp. Prueba otro archivo o un MP4 con H.264 y AAC.',
            ];
        }

        $outSize = filesize($outputPath);
        if ($outSize === false || $outSize > $maxBytes) {
            @unlink($outputPath);

            return [
                'success' => false,
                'error' => 'Tras convertir, el video sigue superando ' . round($maxBytes / 1024 / 1024, 1) . ' MB. Usa un clip más corto.',
            ];
        }

        WaInboxLog::info('videoTranscode.ok', [
            'input_bytes' => filesize($inputPath),
            'output_bytes' => $outSize,
        ]);

        return ['success' => true, 'path' => $outputPath];
    }

    /**
     * @return string|null
     */
    public function resolveFfmpegBinary()
    {
        $configured = trim((string) config('meta_whatsapp.ffmpeg_binary', 'ffmpeg'));
        if ($configured !== '' && $this->binaryWorks($configured)) {
            return $configured;
        }

        if ($this->binaryWorks('ffmpeg')) {
            return 'ffmpeg';
        }

        return null;
    }

    /**
     * @param  string  $binary
     * @return bool
     */
    private function binaryWorks($binary)
    {
        if ($binary === '') {
            return false;
        }

        $out = @shell_exec(escapeshellarg($binary) . ' -version 2>&1');

        return is_string($out) && stripos($out, 'ffmpeg version') !== false;
    }

    /**
     * @param  string  $inputPath
     * @return string
     */
    private function buildOutputPath($inputPath)
    {
        $dir = dirname($inputPath);

        return $dir . DIRECTORY_SEPARATOR . 'wa_meta_' . uniqid('', true) . '.mp4';
    }
}
