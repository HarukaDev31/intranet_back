<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cola Horizon / Redis para jobs de carga consolidada
    |--------------------------------------------------------------------------
    |
    | Debe existir un supervisor en config/horizon.php que escuche esta cola.
    | No usar "default": en este proyecto Horizon no tiene worker para default.
    |
    */
    'queue' => env('CARGA_CONSOLIDADA_QUEUE', 'carga_consolidada'),

    /*
    | Hora de corte (HH:MM) para bloques históricos de CARGA POR CONTACTAR en Excel seguimiento Drive.
    | El scheduler debe usar la misma hora (America/Lima).
    */
    'seguimiento_corte_hora' => env('SEGUIMIENTO_CORTE_HORA', '20:00'),
    'seguimiento_corte_timezone' => env('SEGUIMIENTO_CORTE_TIMEZONE', 'America/Lima'),

    /**
     * Mínimo entre encolados de sync Drive por consolidado (minutos).
     * Agrupa cambios de observers + evita inundar la cola carga_consolidada.
     */
    'seguimiento_sync_debounce_minutes' => (int) env('SEGUIMIENTO_SYNC_DEBOUNCE_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Cache HTTP (Redis) del módulo carga consolidada
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('CARGA_CONSOLIDADA_CACHE_ENABLED', true),
        'ttl_minutes' => (int) env('CARGA_CONSOLIDADA_CACHE_TTL_MINUTES', 3),
        'skip_path_contains' => [
            'download',
            'export',
            'preview',
            '/file',
            'zip',
            'pdf',
            'excel',
            'template',
            'upload',
            'serve',
            'check-temp',
        ],
    ],

];
