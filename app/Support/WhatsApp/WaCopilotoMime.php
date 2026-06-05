<?php

namespace App\Support\WhatsApp;

/**
 * MIME types para wa_copiloto_messages (columna acotada).
 */
class WaCopilotoMime
{
    /**
     * @param  string|null  $mime
     * @return string|null
     */
    public static function normalizeForStorage($mime)
    {
        if ($mime === null) {
            return null;
        }

        $mime = strtolower(trim((string) $mime));
        if ($mime === '') {
            return null;
        }

        $aliases = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'application/vnd.openxmlformats-officedocument.spreadsheetml',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'application/vnd.openxmlformats-officedocument.wordprocessingml',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'application/vnd.openxmlformats-officedocument.presentationml',
        ];

        if (isset($aliases[$mime])) {
            return $aliases[$mime];
        }

        if (strlen($mime) > 127) {
            return substr($mime, 0, 127);
        }

        return $mime;
    }
}
