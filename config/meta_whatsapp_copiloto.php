<?php

return [

    'enabled' => filter_var(env('META_WHATSAPP_COPILOTO_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'graph_api_version' => env('META_WHATSAPP_COPILOTO_GRAPH_VERSION', env('META_WHATSAPP_GRAPH_VERSION', 'v19.0')),

    /** Número Meta principal de Copiloto / ventas. */
    'phone_number_id' => env('META_WHATSAPP_COPILOTO_PHONE_NUMBER_ID', ''),

    /**
     * Token Graph API. Por defecto comparte META_WHATSAPP_ACCESS_TOKEN (inbox/coordinación).
     * META_WHATSAPP_COPILOTO_ACCESS_TOKEN solo si Copiloto usa un token distinto.
     */
    'access_token' => env('META_WHATSAPP_ACCESS_TOKEN', env('META_WHATSAPP_COPILOTO_ACCESS_TOKEN', '')),

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

    /** @deprecated Use analysis_context_days + analysis_recent_messages */
    'analysis_context_messages' => (int) env('META_WHATSAPP_COPILOTO_ANALYSIS_CONTEXT_MESSAGES', 12),

    /** Ventana temporal del chat incluido en el prompt (días). */
    'analysis_context_days' => (int) env('META_WHATSAPP_COPILOTO_ANALYSIS_CONTEXT_DAYS', 14),

    /** Mensajes recientes enviados casi completos al prompt. */
    'analysis_recent_messages' => (int) env('META_WHATSAPP_COPILOTO_ANALYSIS_RECENT_MESSAGES', 8),

    /** Mensajes más antiguos (dentro de la ventana) comprimidos a 1 línea c/u. */
    'analysis_compact_messages' => (int) env('META_WHATSAPP_COPILOTO_ANALYSIS_COMPACT_MESSAGES', 6),

    /** Tope aproximado de caracteres del bloque de contexto (sin contar meta/ficha). */
    'analysis_max_context_chars' => (int) env('META_WHATSAPP_COPILOTO_ANALYSIS_MAX_CONTEXT_CHARS', 3200),

    /** Máximo por línea de mensaje en contexto reciente. */
    'analysis_max_line_chars' => (int) env('META_WHATSAPP_COPILOTO_ANALYSIS_MAX_LINE_CHARS', 260),

    /** Resumen acumulado reutilizable entre análisis (chars). */
    'analysis_summary_max_chars' => (int) env('META_WHATSAPP_COPILOTO_ANALYSIS_SUMMARY_MAX_CHARS', 320),

    /** Peso EMA del puntaje nuevo vs histórico (0.15–0.75). */
    'analysis_lead_score_ema_alpha' => (float) env('META_WHATSAPP_COPILOTO_ANALYSIS_LEAD_SCORE_EMA_ALPHA', 0.4),

    /** Días sin mensaje del cliente antes de aplicar decaimiento al puntaje. */
    'analysis_score_decay_after_days' => (int) env('META_WHATSAPP_COPILOTO_ANALYSIS_SCORE_DECAY_AFTER_DAYS', 7),

    /** Tokens máximos de salida Gemini por análisis. */
    'analysis_gemini_max_output_tokens' => (int) env('META_WHATSAPP_COPILOTO_ANALYSIS_GEMINI_MAX_OUTPUT_TOKENS', 2048),

    /**
     * Teléfonos permitidos para análisis IA (CSV, E.164 sin +). Use * o all para todos.
     * Ej: 51912705923 o 51912705923,51999888777
     */
    'analysis_allowed_phones' => env('META_WHATSAPP_COPILOTO_ANALYSIS_ALLOWED_PHONES', '51912705923'),

    /** Contexto de ventas WON para prompts Gemini (reglas + conversaciones cerradas). */
    'analysis_sales_context_enabled' => filter_var(
        env('META_WHATSAPP_COPILOTO_ANALYSIS_SALES_CONTEXT_ENABLED', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** Ruta relativa en disco local (storage/app). */
    'analysis_sales_context_path' => env('META_WHATSAPP_COPILOTO_ANALYSIS_SALES_CONTEXT_PATH', 'ventas_contexto.txt'),

    /** Tope de caracteres del bloque de conocimiento (reglas + excerpts WON). */
    'analysis_sales_context_max_chars' => (int) env('META_WHATSAPP_COPILOTO_ANALYSIS_SALES_CONTEXT_MAX_CHARS', 18000),

    /** Cantidad máxima de ventas WON de ejemplo en el prompt. */
    'analysis_sales_context_max_sections' => (int) env('META_WHATSAPP_COPILOTO_ANALYSIS_SALES_CONTEXT_MAX_SECTIONS', 6),

    /** Máximo de caracteres por venta WON de ejemplo. */
    'analysis_sales_context_section_max_chars' => (int) env('META_WHATSAPP_COPILOTO_ANALYSIS_SALES_CONTEXT_SECTION_MAX_CHARS', 2000),

    /** TTL cache del bloque de conocimiento (segundos). */
    'analysis_sales_context_cache_ttl' => (int) env('META_WHATSAPP_COPILOTO_ANALYSIS_SALES_CONTEXT_CACHE_TTL', 86400),

];
