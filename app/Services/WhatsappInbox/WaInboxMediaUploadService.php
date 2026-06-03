<?php

namespace App\Services\WhatsappInbox;

use App\Support\WhatsApp\CoordinacionMediaLink;
use App\Support\WhatsApp\WaInboxLog;
use App\Support\WhatsApp\WaInboxVideoTranscoder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WaInboxMediaUploadService
{
    /**
     * @param  UploadedFile  $file
     * @param  string  $kind  image|video|document|audio
     * @param  string  $errorMessage
     * @return array{type: string, link: string, path: string, filename: string, mime: string}|null
     */
    public function uploadFromFile(UploadedFile $file, $kind, &$errorMessage = '')
    {
        $errorMessage = '';
        $kind = strtolower(trim((string) $kind));
        if (!in_array($kind, ['image', 'video', 'document', 'audio'], true)) {
            $kind = $this->guessKindFromFile($file);
        }

        $sizeError = $this->validateFileSize($file, $kind);
        if ($sizeError !== null) {
            $errorMessage = $sizeError;

            return null;
        }

        if ($kind === 'document') {
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if ($ext === '') {
                $errorMessage = 'Formato de documento no reconocido.';

                return null;
            }
        }

        $storageKey = CoordinacionMediaLink::META_TEMP_PREFIX . '/inbox/outbound/'
            . Str::uuid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());

        $localRelative = $file->store('temp/wa-inbox-uploads', 'local');
        if ($localRelative === false) {
            $errorMessage = 'No se pudo guardar el archivo temporal.';

            return null;
        }

        $fullPath = storage_path('app/' . $localRelative);
        $uploadPath = $fullPath;
        $uploadFilename = $file->getClientOriginalName();
        $transcodedPath = null;

        if ($kind === 'video') {
            @set_time_limit(max(180, (int) config('meta_whatsapp.video_transcode_timeout', 120) + 30));
            $transcoder = new WaInboxVideoTranscoder();
            $transcode = $transcoder->transcodeForWhatsAppTemplate($fullPath);
            if (!empty($transcode['success']) && !empty($transcode['path'])) {
                $transcodedPath = (string) $transcode['path'];
                $uploadPath = $transcodedPath;
                $uploadFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.mp4';
            } else {
                $errorMessage = isset($transcode['error']) ? (string) $transcode['error'] : 'No se pudo convertir el video';

                try {
                    Storage::disk('local')->delete($localRelative);
                } catch (\Exception $e) {
                    // ignorar
                }

                return null;
            }
        }

        $url = CoordinacionMediaLink::uploadLocalFile($uploadPath, $storageKey);

        try {
            Storage::disk('local')->delete($localRelative);
            if ($transcodedPath !== null && is_file($transcodedPath)) {
                @unlink($transcodedPath);
            }
        } catch (\Exception $e) {
            // ignorar limpieza
        }

        if ($url === null || $url === '') {
            WaInboxLog::error('waInboxMediaUpload.s3_failed', [
                'kind' => $kind,
                'name' => $file->getClientOriginalName(),
            ]);
            $errorMessage = 'No se pudo subir el archivo a S3.';

            return null;
        }

        WaInboxLog::info('waInboxMediaUpload.ok', [
            'kind' => $kind,
            'storage_key' => $storageKey,
            'size_bytes' => $file->getSize(),
        ]);

        return [
            'type' => $kind,
            'link' => $url,
            'path' => $storageKey,
            'filename' => $uploadFilename,
            'mime' => (string) $file->getMimeType(),
        ];
    }

    /**
     * @param  UploadedFile  $file
     * @return string
     */
    public function guessKindFromFile(UploadedFile $file)
    {
        $mime = strtolower((string) $file->getMimeType());
        if (strpos($mime, 'image/') === 0) {
            return 'image';
        }
        if (strpos($mime, 'video/') === 0) {
            return 'video';
        }
        if (strpos($mime, 'audio/') === 0) {
            return 'audio';
        }

        return 'document';
    }

    /**
     * @param  UploadedFile  $file
     * @param  string  $kind
     * @return string|null
     */
    private function validateFileSize(UploadedFile $file, $kind)
    {
        $limits = config('meta_whatsapp.inbox_media_max_bytes', []);
        $key = in_array($kind, ['image', 'video', 'document', 'audio'], true) ? $kind : 'document';

        if ($key === 'video') {
            $max = (int) config('meta_whatsapp.inbox_header_max_video_input_bytes', 80 * 1024 * 1024);
        } else {
            $max = isset($limits[$key]) ? (int) $limits[$key] : 0;
            if ($max <= 0) {
                $fallback = config('meta_whatsapp.inbox_header_max_bytes', []);
                $max = isset($fallback[$key]) ? (int) $fallback[$key] : 0;
            }
        }

        if ($max <= 0) {
            return null;
        }

        $size = (int) $file->getSize();
        if ($size <= $max) {
            return null;
        }

        $maxMb = round($max / 1024 / 1024, 1);
        $fileMb = round($size / 1024 / 1024, 1);

        return 'El archivo pesa ' . $fileMb . ' MB. WhatsApp permite máximo ' . $maxMb . ' MB para este tipo.';
    }
}
