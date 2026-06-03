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

    /** Límites para media libre (ventana abierta) en el inbox. */
    'inbox_media_max_bytes' => [
        'image' => (int) env('META_WHATSAPP_INBOX_MAX_IMAGE_BYTES', 5 * 1024 * 1024),
        'video' => (int) env('META_WHATSAPP_INBOX_MAX_VIDEO_BYTES', 16 * 1024 * 1024),
        'document' => (int) env('META_WHATSAPP_INBOX_MAX_DOCUMENT_BYTES', 100 * 1024 * 1024),
        'audio' => (int) env('META_WHATSAPP_INBOX_MAX_AUDIO_BYTES', 16 * 1024 * 1024),
    ],

    /** Tamaño máximo del archivo original antes de transcodificar video (bytes). */
    'inbox_header_max_video_input_bytes' => (int) env('META_WHATSAPP_INBOX_MAX_VIDEO_INPUT_BYTES', 80 * 1024 * 1024),

    /** Ruta al binario ffmpeg (debe estar instalado en el servidor). */
    'ffmpeg_binary' => env('META_WHATSAPP_FFMPEG_BINARY', 'ffmpeg'),

    'video_transcode_enabled' => filter_var(env('META_WHATSAPP_VIDEO_TRANSCODE', true), FILTER_VALIDATE_BOOLEAN),

    'video_transcode_timeout' => (int) env('META_WHATSAPP_VIDEO_TRANSCODE_TIMEOUT', 120),

    'inbox_queue' => env('META_WHATSAPP_INBOX_QUEUE', env('META_WHATSAPP_QUEUE', 'notificaciones')),

    /** Pausa entre envíos Meta encadenados (batch 2) para respetar orden en el teléfono del cliente. */
    'inbox_outbound_step_delay_seconds' => (int) env('META_WHATSAPP_INBOX_OUTBOUND_STEP_DELAY', 2),

    /**
     * Automatización coordinación: texto del chat = cuerpo de plantilla Meta (Graph) + body_parameters.
     * Si false, usa chat_preview/bitrix_message del payload (comportamiento legacy).
     */
    'coordinacion_inbox_preview_from_template' => filter_var(
        env('META_WHATSAPP_COORDINACION_PREVIEW_FROM_TEMPLATE', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Dominio para setDatabaseConnection en jobs WaInbox (cola sin HTTP).
     * En local/WSL suele ser localhost → mysql_local.
     */
    'inbox_job_domain' => env('META_WHATSAPP_INBOX_JOB_DOMAIN', 'localhost'),

    /** WebSocket/Pusher en tiempo real; si false, no se emite broadcast (el envío Meta sigue). */
    'inbox_broadcast_enabled' => filter_var(env('META_WHATSAPP_INBOX_BROADCAST', true), FILTER_VALIDATE_BOOLEAN),

    'inbox_display_number' => env('META_WHATSAPP_INBOX_DISPLAY_NUMBER', ''),
    'inbox_alert_phone' => env('WA_INBOX_ALERT_PHONE', ''),

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
