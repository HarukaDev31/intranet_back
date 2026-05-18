<?php

return array(
    /*
    | TTL de cache Soporte TI (segundos). Usa el driver CACHE_DRIVER de Laravel (file, redis, etc.).
    */
    'cache_ttl_list_seconds' => (int) env('SOPORTE_TI_CACHE_TTL_LIST', 120),
    'cache_ttl_show_seconds' => (int) env('SOPORTE_TI_CACHE_TTL_SHOW', 180),
    'cache_ttl_mensajes_seconds' => (int) env('SOPORTE_TI_CACHE_TTL_MENSAJES', 60),
    'cache_ttl_catalog_seconds' => (int) env('SOPORTE_TI_CACHE_TTL_CATALOG', 3600),
);
