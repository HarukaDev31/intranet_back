<?php

namespace App\Services\WaCopiloto;

use App\Support\WhatsApp\CoordinacionMediaLink;
use App\Support\WhatsApp\WaCopilotoLog;
use App\Support\WhatsApp\WaCopilotoMime;
use App\Support\WhatsApp\WaInboxImageCompressor;
use App\Support\WhatsApp\WaInboxVideoTranscoder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class WaCopilotoMediaUploadService
{
    /**
     * @param  UploadedFile  $file
     * @param  string  $kind  image|video|document|audio
     * @param  string  $errorMessage
     * @param  int  $conversationId
     * @return array{type: string, path: string, filename: string, mime: string}|null
     */
    public function uploadFromFile(UploadedFile $file, $kind, &$errorMessage = '', $conversationId = 0)
    {
        $errorMessage = '';
        $conversationId = (int) $conversationId;
        if ($conversationId <= 0) {
            $errorMessage = 'Conversación no válida para subir el archivo.';

            return null;
        }

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

        $storageKey = CoordinacionMediaLink::buildCopilotoConversationStorageKey(
            $conversationId,
            $kind,
            $file->getClientOriginalName()
        );

        $localRelative = $file->store('temp/wa-inbox-uploads', 'local');
        if ($localRelative === false) {
            $errorMessage = 'No se pudo guardar el archivo temporal.';

            return null;
        }

        $fullPath = storage_path('app/' . $localRelative);
        $uploadPath = $fullPath;
        $uploadFilename = $file->getClientOriginalName();
        $transcodedPath = null;
        $compressedPath = null;
        $overrideMime = null;

        if ($kind === 'image') {
            $compressor = new WaInboxImageCompressor();
            $compress = $compressor->compressForWhatsApp($fullPath);
            if (empty($compress['success'])) {
                $errorMessage = isset($compress['error'])
                    ? (string) $compress['error']
                    : 'No se pudo comprimir la imagen para WhatsApp.';

                try {
                    Storage::disk('local')->delete($localRelative);
                } catch (\Exception $e) {
                    // ignorar
                }

                return null;
            }

            if (!empty($compress['recompressed']) && !empty($compress['path'])) {
                $compressedPath = (string) $compress['path'];
                $uploadPath = $compressedPath;
                $overrideMime = isset($compress['mime']) ? (string) $compress['mime'] : 'image/jpeg';
                $uploadFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.jpg';
                $storageKey = CoordinacionMediaLink::buildCopilotoConversationStorageKey(
                    $conversationId,
                    $kind,
                    $uploadFilename
                );
            }
        }

        if ($kind === 'video') {
            @set_time_limit(max(180, (int) config('meta_whatsapp_copiloto.video_transcode_timeout', 120) + 30));
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

        $uploaded = CoordinacionMediaLink::uploadLocalFileToStorage($uploadPath, $storageKey);

        try {
            Storage::disk('local')->delete($localRelative);
            if ($transcodedPath !== null && is_file($transcodedPath)) {
                @unlink($transcodedPath);
            }
            if ($compressedPath !== null && is_file($compressedPath)) {
                @unlink($compressedPath);
            }
        } catch (\Exception $e) {
            // ignorar limpieza
        }

        if ($uploaded === null || ($uploaded['path'] ?? '') === '') {
            WaCopilotoLog::error('WaCopilotoMediaUpload.s3_failed', [
                'kind' => $kind,
                'name' => $file->getClientOriginalName(),
                'conversation_id' => $conversationId,
                'storage_key' => $storageKey,
            ]);
            $errorMessage = 'No se pudo subir el archivo a S3.';

            return null;
        }

        $storedPath = (string) $uploaded['path'];
        if ($kind === 'video') {
            $mime = 'video/mp4';
        } elseif ($overrideMime !== null) {
            $mime = $overrideMime;
        } else {
            $mime = (string) $file->getMimeType();
        }
        $mime = WaCopilotoMime::normalizeForStorage($mime);

        WaCopilotoLog::info('WaCopilotoMediaUpload.ok', [
            'kind' => $kind,
            'storage_key' => $storedPath,
            'conversation_id' => $conversationId,
            'size_bytes' => $file->getSize(),
        ]);

        return [
            'type' => $kind,
            'path' => $storedPath,
            'filename' => $uploadFilename,
            'mime' => $mime,
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
        $limits = config('meta_whatsapp_copiloto.media_max_bytes', []);
        $key = in_array($kind, ['image', 'video', 'document', 'audio'], true) ? $kind : 'document';

        if ($key === 'video') {
            $max = (int) config('meta_whatsapp_copiloto.header_max_video_input_bytes', 80 * 1024 * 1024);
        } elseif ($key === 'image' && config('meta_whatsapp.image_compress_enabled', true)) {
            // La imagen se recomprime antes de subir; aceptar un original mayor al tope Meta.
            $max = (int) config('meta_whatsapp.inbox_header_max_image_input_bytes', 40 * 1024 * 1024);
        } else {
            $max = isset($limits[$key]) ? (int) $limits[$key] : 0;
            if ($max <= 0) {
                $fallback = config('meta_whatsapp_copiloto.header_max_bytes', []);
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
