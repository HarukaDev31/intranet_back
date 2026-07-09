<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // Laravel HandleCors (Kernel) + preflight OPTIONS en Nginx host (ver host-reverse-proxy.probusiness.example.conf)

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'files/*', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://probusiness.pe',
        'http://probusiness.pe',
        'https://carga-consolidada.probusiness.pe',
        'https://clientes.probusiness.pe',
        'https://app.probusiness.pe',
        'https://admin.probusiness.pe',
        'https://qaintranet.probusiness.pe',
        'https://intranetback.probusiness.pe',
        'https://intranetv2.probusiness.pe',
    ],

    // ✅ Subdominios Y dominio raíz probusiness.pe (el patrón .*\.probusiness.pe NO cubre el apex)
    'allowed_origins_patterns' => [
        '#^https?://(?:[a-zA-Z0-9-]+\.)?probusiness\.pe(:\d+)?$#',
        '#^http://localhost(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
