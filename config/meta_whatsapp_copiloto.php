<?php

return [

    'enabled' => filter_var(env('META_WHATSAPP_COPILOTO_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'graph_api_version' => env('META_WHATSAPP_COPILOTO_GRAPH_VERSION', env('META_WHATSAPP_GRAPH_VERSION', 'v19.0')),

    /** Número Meta principal de Copiloto / ventas. */
    'phone_number_id' => env('META_WHATSAPP_COPILOTO_PHONE_NUMBER_ID', ''),

    'access_token' => env('META_WHATSAPP_COPILOTO_ACCESS_TOKEN', env('META_WHATSAPP_ACCESS_TOKEN')),

    'app_secret' => env('META_WHATSAPP_COPILOTO_APP_SECRET', env('META_WHATSAPP_APP_SECRET')),

    'webhook_verify_token' => env(
        'META_WHATSAPP_COPILOTO_WEBHOOK_VERIFY_TOKEN',
        env('META_WHATSAPP_WEBHOOK_VERIFY_TOKEN')
    ),

    'waba_id' => env('META_WHATSAPP_COPILOTO_WABA_ID', env('META_WHATSAPP_COPILOTO_USE_INBOX_WABA', false) ? env('META_WHATSAPP_WABA_ID') : ''),

    /**
     * Prefijos de plantillas Meta propias de Copiloto / ventas (cuando comparten WABA con coordinación).
     * CSV en META_WHATSAPP_COPILOTO_TEMPLATE_PREFIXES
     */
    'template_prefixes' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'META_WHATSAPP_COPILOTO_TEMPLATE_PREFIXES',
        'pb_ventas_,pb_copiloto_,copiloto_'
    ))))),

    /** Excluir plantillas operativas de coordinación al listar en Copiloto. */
    'template_exclude_prefixes' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'META_WHATSAPP_COPILOTO_TEMPLATE_EXCLUDE_PREFIXES',
        'pb_proveedor_,pb_inspeccion_,pb_rotulado_,pb_docs_,pb_entrega_'
    ))))),

    /** Si true y no hay prefijos configurados, solo excluye coordinación (no exige prefijo copiloto). */
    'template_filter_exclude_only' => filter_var(
        env('META_WHATSAPP_COPILOTO_TEMPLATE_FILTER_EXCLUDE_ONLY', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'default_language' => env('META_WHATSAPP_COPILOTO_LANGUAGE', env('META_WHATSAPP_LANGUAGE', 'es_PE')),

    'display_number' => env('META_WHATSAPP_COPILOTO_DISPLAY_NUMBER', ''),

    /** Slug de sesión por defecto (wa_copiloto_sessions.slug). */
    'default_session_slug' => env('META_WHATSAPP_COPILOTO_SESSION_SLUG', 'ventas'),

    'header_max_bytes' => [
        'image' => (int) env('META_WHATSAPP_COPILOTO_MAX_IMAGE_BYTES', 5 * 1024 * 1024),
        'video' => (int) env('META_WHATSAPP_COPILOTO_MAX_VIDEO_BYTES', 16 * 1024 * 1024),
        'document' => (int) env('META_WHATSAPP_COPILOTO_MAX_DOCUMENT_BYTES', 100 * 1024 * 1024),
    ],

    'media_max_bytes' => [
        'image' => (int) env('META_WHATSAPP_COPILOTO_MAX_IMAGE_BYTES', 5 * 1024 * 1024),
        'video' => (int) env('META_WHATSAPP_COPILOTO_MAX_VIDEO_BYTES', 16 * 1024 * 1024),
        'document' => (int) env('META_WHATSAPP_COPILOTO_MAX_DOCUMENT_BYTES', 100 * 1024 * 1024),
        'audio' => (int) env('META_WHATSAPP_COPILOTO_MAX_AUDIO_BYTES', 16 * 1024 * 1024),
    ],

    'header_max_video_input_bytes' => (int) env('META_WHATSAPP_COPILOTO_MAX_VIDEO_INPUT_BYTES', 80 * 1024 * 1024),

    'ffmpeg_binary' => env('META_WHATSAPP_COPILOTO_FFMPEG_BINARY', env('META_WHATSAPP_FFMPEG_BINARY', 'ffmpeg')),

    'video_transcode_enabled' => filter_var(
        env('META_WHATSAPP_COPILOTO_VIDEO_TRANSCODE', env('META_WHATSAPP_VIDEO_TRANSCODE', true)),
        FILTER_VALIDATE_BOOLEAN
    ),

    'video_transcode_timeout' => (int) env(
        'META_WHATSAPP_COPILOTO_VIDEO_TRANSCODE_TIMEOUT',
        env('META_WHATSAPP_VIDEO_TRANSCODE_TIMEOUT', 120)
    ),

    'queue' => env('META_WHATSAPP_COPILOTO_QUEUE', env('META_WHATSAPP_QUEUE', 'notificaciones')),

    'outbound_step_delay_seconds' => (int) env('META_WHATSAPP_COPILOTO_OUTBOUND_STEP_DELAY', 2),

    'preview_from_template' => filter_var(
        env('META_WHATSAPP_COPILOTO_PREVIEW_FROM_TEMPLATE', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'job_domain' => env('META_WHATSAPP_COPILOTO_JOB_DOMAIN', env('META_WHATSAPP_INBOX_JOB_DOMAIN', '')),

    'broadcast_enabled' => filter_var(env('META_WHATSAPP_COPILOTO_BROADCAST', true), FILTER_VALIDATE_BOOLEAN),

    'session_message_when_window_open' => filter_var(
        env('META_WHATSAPP_COPILOTO_SESSION_WHEN_WINDOW_OPEN', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'empty_body_parameter_placeholder' => env(
        'META_WHATSAPP_COPILOTO_EMPTY_PARAM_PLACEHOLDER',
        env('META_WHATSAPP_EMPTY_PARAM_PLACEHOLDER', '—')
    ),

    'fallback_public_asset_url' => filter_var(
        env('META_WHATSAPP_COPILOTO_FALLBACK_PUBLIC_URL', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** Análisis IA (Gemini) de mensajes entrantes del cliente. */
    'analysis_enabled' => filter_var(env('META_WHATSAPP_COPILOTO_ANALYSIS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'analysis_queue' => env('META_WHATSAPP_COPILOTO_ANALYSIS_QUEUE', env('META_WHATSAPP_COPILOTO_QUEUE', env('META_WHATSAPP_QUEUE', 'notificaciones'))),

    'analysis_context_messages' => (int) env('META_WHATSAPP_COPILOTO_ANALYSIS_CONTEXT_MESSAGES', 12),

];
