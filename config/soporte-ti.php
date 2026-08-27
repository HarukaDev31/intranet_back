<?php

return array(
    /*
    | Cola Horizon/Redis para jobs y broadcasts del módulo Soporte TI.
    */
    'queue' => env('SOPORTE_TI_QUEUE', 'soporte_ti'),

    /*
    | TTL de cache Soporte TI (segundos). Usa el driver CACHE_DRIVER de Laravel (file, redis, etc.).
    */
    'cache_ttl_list_seconds' => (int) env('SOPORTE_TI_CACHE_TTL_LIST', 120),
    'cache_ttl_show_seconds' => (int) env('SOPORTE_TI_CACHE_TTL_SHOW', 180),
    'cache_ttl_mensajes_seconds' => (int) env('SOPORTE_TI_CACHE_TTL_MENSAJES', 60),
    'cache_ttl_catalog_seconds' => (int) env('SOPORTE_TI_CACHE_TTL_CATALOG', 3600),

    /*
    | Si true, valida cambios de estado contra soporte_ti_estado_transiciones
    | (además de las reglas de rol / En progreso). Default false para no romper
    | saltos de kanban del staff hasta afinar el grafo.
    */
    'enforce_transiciones' => (bool) env('SOPORTE_TI_ENFORCE_TRANSICIONES', false),

    /*
    | WhatsApp: avisos al grupo de Soporte (creado / en_progreso / desplegado / observado).
    | Textos en App\Support\SoporteTi\SoporteTiWhatsappGrupoMensajeBuilder (plantilla MSJS).
    | from_number = instancia en redis.probusiness.pe (igual que "administracion").
    */
    'whatsapp_enabled' => (bool) env('SOPORTE_TI_WHATSAPP_ENABLED', true),
    'whatsapp_from_number' => env('SOPORTE_TI_WHATSAPP_FROM', 'soporte'),
    'whatsapp_group_id' => env('SOPORTE_TI_WHATSAPP_GROUP', '120363402844775385@g.us'),
    'whatsapp_queue' => env('SOPORTE_TI_WHATSAPP_QUEUE', 'notificaciones'),
);
