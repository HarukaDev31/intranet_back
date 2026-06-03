<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Coordinación (WABA consolidado / fromNumber por defecto)
    |--------------------------------------------------------------------------
    */
    'coordinacion_enabled' => filter_var(env('META_WHATSAPP_COORDINACION_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /** Si true y no hay plantilla en el payload, usa redis.probusiness.pe (transición). */
    'legacy_fallback' => filter_var(env('META_WHATSAPP_LEGACY_FALLBACK', true), FILTER_VALIDATE_BOOLEAN),

    'graph_api_version' => env('META_WHATSAPP_GRAPH_VERSION', 'v19.0'),
    'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID', '1062249786981832'),
    'access_token' => env('META_WHATSAPP_ACCESS_TOKEN'),

    /** App Secret — firma POST webhook (X-Hub-Signature-256). */
    'app_secret' => env('META_WHATSAPP_APP_SECRET'),

    /** Verify Token — GET hub challenge al suscribir webhook en Meta. */
    'webhook_verify_token' => env('META_WHATSAPP_WEBHOOK_VERIFY_TOKEN'),

    /** WABA ID opcional para listar plantillas vía Graph. */
    'waba_id' => env('META_WHATSAPP_WABA_ID'),

    'default_language' => env('META_WHATSAPP_LANGUAGE', 'es_PE'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Inbox (intranet coordinación)
    |--------------------------------------------------------------------------
    */
    /** Límites Meta para media en encabezado de plantilla (bytes). */
    'inbox_header_max_bytes' => [
        'image' => (int) env('META_WHATSAPP_INBOX_MAX_IMAGE_BYTES', 5 * 1024 * 1024),
        'video' => (int) env('META_WHATSAPP_INBOX_MAX_VIDEO_BYTES', 16 * 1024 * 1024),
        'document' => (int) env('META_WHATSAPP_INBOX_MAX_DOCUMENT_BYTES', 100 * 1024 * 1024),
    ],

    /** Tamaño máximo del archivo original antes de transcodificar video (bytes). */
    'inbox_header_max_video_input_bytes' => (int) env('META_WHATSAPP_INBOX_MAX_VIDEO_INPUT_BYTES', 80 * 1024 * 1024),

    /** Ruta al binario ffmpeg (debe estar instalado en el servidor). */
    'ffmpeg_binary' => env('META_WHATSAPP_FFMPEG_BINARY', 'ffmpeg'),

    'video_transcode_enabled' => filter_var(env('META_WHATSAPP_VIDEO_TRANSCODE', true), FILTER_VALIDATE_BOOLEAN),

    'video_transcode_timeout' => (int) env('META_WHATSAPP_VIDEO_TRANSCODE_TIMEOUT', 120),

    'inbox_queue' => env('META_WHATSAPP_INBOX_QUEUE', env('META_WHATSAPP_QUEUE', 'notificaciones')),
    'inbox_display_number' => env('META_WHATSAPP_INBOX_DISPLAY_NUMBER', ''),
    'inbox_alert_phone' => env('WA_INBOX_ALERT_PHONE', ''),

    /*
    |--------------------------------------------------------------------------
    | Bitrix Open Lines — línea coordinación (consolidado)
    |--------------------------------------------------------------------------
    */
    'bitrix_line_id' => (int) env('META_WHATSAPP_BITRIX_LINE_ID', 39),
    'bitrix_user_id' => (int) env('META_WHATSAPP_BITRIX_USER_ID', 181),
    'bitrix_webhook_intercept' => env('BITRIX_WEBHOOK_INTERCEPT'),

    /**
     * Filtro de chat CRM para imopenlines.crm.message.add (la API no acepta LINE_ID).
     * CONNECTOR_TITLE del canal en Bitrix que corresponde a coordinación/consolidado.
     * Varias palabras separadas por coma; coincide si el título contiene alguna (sin distinguir mayúsculas).
     */
    'bitrix_connector_match' => array_values(array_filter(array_map('trim', explode(
        ',',
        (string) env(
            'META_WHATSAPP_BITRIX_CONNECTOR_MATCH',
            'coordinación,coordinacion,consolidado,powerapp,whatcrm,whatsapp'
        )
    )))),

    /** Si el CONNECTOR_TITLE contiene alguna de estas palabras, no se usa ese chat (ej. ventas). */
    'bitrix_connector_exclude' => array_values(array_filter(array_map('trim', explode(
        ',',
        (string) env('META_WHATSAPP_BITRIX_CONNECTOR_EXCLUDE', 'ventas')
    )))),

    /**
     * Tras Meta OK: imopenlines.crm.message.add en línea 39 (texto bitrix_message para operadores).
     * Requerido para que el chat de coordinación muestre lo enviado.
     * Si el cliente recibe el mismo texto dos veces en WhatsApp, el conector de la línea 39
     * está reenviando: revisar configuración del canal en Bitrix o contactar soporte Bitrix.
     */
    'bitrix_register_openline_message' => filter_var(
        env('META_WHATSAPP_BITRIX_REGISTER_OPENLINE_MESSAGE', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** Si Meta falla, igual registrar en línea 39 para que coordinación vea el intento. */
    'bitrix_register_openline_on_meta_failure' => filter_var(
        env('META_WHATSAPP_BITRIX_REGISTER_OPENLINE_ON_META_FAILURE', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** Comentario extra en timeline CRM además del open line (cuando hay CHAT_ID). */
    'bitrix_timeline_log' => filter_var(env('META_WHATSAPP_BITRIX_TIMELINE_LOG', false), FILTER_VALIDATE_BOOLEAN),

    /**
     * Si no hay CHAT_ID (sin sesión / conector no reconocido), guardar bitrix_message en timeline del contacto.
     * No abre conversación en línea 39 ni reenvía WhatsApp; solo historial en la ficha CRM.
     */
    'bitrix_fallback_timeline_when_no_chat' => filter_var(
        env('META_WHATSAPP_BITRIX_FALLBACK_TIMELINE_NO_CHAT', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** Cola del job que registra en Bitrix open line (tras envío Meta). */
    'bitrix_register_queue' => env('META_WHATSAPP_BITRIX_REGISTER_QUEUE', env('META_WHATSAPP_QUEUE', 'notificaciones')),

    /** Reintentos máximos en tabla whatsapp_coordinacion_bitrix_registros (no se vuelve a encolar). */
    'bitrix_register_max_attempts' => (int) env('META_WHATSAPP_BITRIX_REGISTER_MAX_ATTEMPTS', 3),

    'queue' => env('META_WHATSAPP_QUEUE', 'notificaciones'),

    /** Valor enviado a Meta cuando un parámetro de plantilla llega vacío (Meta rechaza text sin valor). */
    'empty_body_parameter_placeholder' => env('META_WHATSAPP_EMPTY_PARAM_PLACEHOLDER', '—'),

    /**
     * Si falla PutObject en S3, intentar URL pública bajo APP_URL para archivos en public/
     * (útil en local; en producción conviene arreglar IAM s3:PutObject en temp/whatsapp-meta/*).
     */
    'fallback_public_asset_url' => filter_var(
        env('META_WHATSAPP_FALLBACK_PUBLIC_URL', true),
        FILTER_VALIDATE_BOOLEAN
    ),

];
