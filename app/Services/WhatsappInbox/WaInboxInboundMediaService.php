<?php

namespace App\Services\WhatsappInbox;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Support\WhatsApp\CoordinacionMediaLink;
use App\Support\WhatsApp\WaInboxLog;
use App\Support\WhatsApp\WaInboxMime;
use Illuminate\Support\Facades\Http;

/**
 * Descarga media de mensajes entrantes Meta y la guarda en S3 (clave relativa en BD).
 */
class WaInboxInboundMediaService
{
    /**
     * @param  array<string, mixed>  $msg  Payload message del webhook
     * @param  int  $conversationId
     * @return array<string, mixed>|null
     */
    public function downloadAndStore(array $msg, $conversationId)
    {
        $parsed = $this->parseMediaFromWebhook($msg);
        if ($parsed === null) {
            return null;
        }

        $token = trim((string) config('meta_whatsapp.access_token'));
        if ($token === '') {
            WaInboxLog::warning('inboundMedia.no_token');

            return null;
        }

        $version = trim((string) config('meta_whatsapp.graph_api_version', 'v19.0'));
        $mediaId = $parsed['media_id'];

        try {
            $meta = Http::withToken($token)
                ->timeout(30)
                ->get("https://graph.facebook.com/{$version}/{$mediaId}");

            if (!$meta->successful()) {
                WaInboxLog::warning('inboundMedia.meta_failed', [
                    'media_id' => $mediaId,
                    'status' => $meta->status(),
                ]);

                return null;
            }

            $downloadUrl = (string) ($meta->json('url') ?? '');
            $mime = (string) ($meta->json('mime_type') ?? $parsed['mime']);
            if ($mime === '') {
                $mime = 'application/octet-stream';
            }

            $sizeBytes = (int) ($meta->json('file_size') ?? 0);

            if ($downloadUrl === '') {
                return null;
            }

            if ($sizeBytes > 0 && !$this->withinSizeLimit($parsed['kind'], $sizeBytes)) {
                WaInboxLog::warning('inboundMedia.too_large', [
                    'kind' => $parsed['kind'],
                    'size' => $sizeBytes,
                ]);

                return null;
            }

            $fileRes = Http::withToken($token)->timeout(120)->get($downloadUrl);
            if (!$fileRes->successful()) {
                WaInboxLog::warning('inboundMedia.download_failed', [
                    'media_id' => $mediaId,
                    'status' => $fileRes->status(),
                ]);

                return null;
            }

            $contents = $fileRes->body();
            if ($contents === '') {
                return null;
            }

            $actualSize = strlen($contents);
            if (!$this->withinSizeLimit($parsed['kind'], $actualSize)) {
                WaInboxLog::warning('inboundMedia.too_large', [
                    'kind' => $parsed['kind'],
                    'size' => $actualSize,
                ]);

                return null;
            }

            $storageKey = CoordinacionMediaLink::buildInboxInboundStorageKey(
                $conversationId,
                $parsed['kind'],
                $parsed['filename']
            );

            $storage = app(ObjectStorageConnectorInterface::class);
            $storageKey = $storage->putContents(ltrim($storageKey, '/'), $contents);

            $body = $parsed['caption'];
            if ($body === '' && $parsed['kind'] === 'document') {
                $body = $parsed['filename'];
            }

            $sizeStored = $sizeBytes > 0 ? $sizeBytes : $actualSize;

            WaInboxLog::info('inboundMedia.ok', [
                'conversation_id' => (int) $conversationId,
                'kind' => $parsed['kind'],
                'storage_key' => $storageKey,
                'size_bytes' => $sizeStored,
            ]);

            return [
                'path' => $storageKey,
                'mime' => WaInboxMime::normalizeForStorage($mime),
                'filename' => $parsed['filename'],
                'body' => $body,
                'size_bytes' => $sizeStored,
                'message_type' => $parsed['kind'],
                'template_params' => [
                    '_media_filename' => $parsed['filename'],
                    '_media_size_bytes' => $sizeStored,
                ],
            ];
        } catch (\Throwable $e) {
            WaInboxLog::error('inboundMedia.exception', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $msg
     * @return array<string, string>|null
     */
    private function parseMediaFromWebhook(array $msg)
    {
        $waType = isset($msg['type']) ? (string) $msg['type'] : '';
        $blockKey = $waType;
        $kind = $waType;

        if ($waType === 'sticker') {
            $kind = 'image';
            $blockKey = 'sticker';
        } elseif (!in_array($waType, ['image', 'video', 'document', 'audio'], true)) {
            return null;
        }

        if (!isset($msg[$blockKey]) || !is_array($msg[$blockKey])) {
            return null;
        }

        $block = $msg[$blockKey];
        $mediaId = isset($block['id']) ? trim((string) $block['id']) : '';
        if ($mediaId === '') {
            return null;
        }

        $caption = isset($block['caption']) ? trim((string) $block['caption']) : '';
        $mime = isset($block['mime_type']) ? trim((string) $block['mime_type']) : '';

        $filename = $this->defaultFilename($kind, $mime, $block);

        return [
            'media_id' => $mediaId,
            'kind' => $kind,
            'caption' => $caption,
            'mime' => $mime,
            'filename' => $filename,
        ];
    }

    /**
     * @param  string  $kind
     * @param  string  $mime
     * @param  array<string, mixed>  $block
     * @return string
     */
    private function defaultFilename($kind, $mime, array $block)
    {
        if ($kind === 'document' && !empty($block['filename'])) {
            $name = (string) $block['filename'];
        } elseif ($kind === 'image') {
            $name = 'image.' . $this->extensionFromMime($mime, 'jpg');
        } elseif ($kind === 'video') {
            $name = 'video.' . $this->extensionFromMime($mime, 'mp4');
        } elseif ($kind === 'audio') {
            $name = !empty($block['voice']) ? 'voice.ogg' : 'audio.' . $this->extensionFromMime($mime, 'ogg');
        } else {
            $name = 'media.bin';
        }

        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);

        return $safe !== '' ? $safe : 'media.bin';
    }

    /**
     * @param  string  $mime
     * @param  string  $fallback
     * @return string
     */
    private function extensionFromMime($mime, $fallback)
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'application/pdf' => 'pdf',
        ];

        $mime = strtolower(trim((string) $mime));

        return isset($map[$mime]) ? $map[$mime] : $fallback;
    }

    /**
     * @param  string  $kind
     * @param  int  $sizeBytes
     * @return bool
     */
    private function withinSizeLimit($kind, $sizeBytes)
    {
        $limits = config('meta_whatsapp.inbox_media_max_bytes', []);
        $max = isset($limits[$kind]) ? (int) $limits[$kind] : 0;
        if ($max <= 0) {
            $fallback = config('meta_whatsapp.inbox_header_max_bytes', []);
            $max = isset($fallback[$kind]) ? (int) $fallback[$kind] : 0;
        }

        if ($max <= 0) {
            return true;
        }

        return $sizeBytes <= $max;
    }
}
