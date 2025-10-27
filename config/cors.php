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

    // ============================================
    // IMPORTANTE: CORS se configura en Nginx
    // ============================================
    // Desactivamos CORS aquí porque ya está configurado en Nginx
    // con subdominios de probusiness.pe
    
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'files/*', 'storage/*'],

    'allowed_methods' => ['*'],

    // ✅ Permitir EXPLÍCITAMENTE los dominios que necesitas
    'allowed_origins' => [
        'https://clientes.probusiness.pe',
        'https://app.probusiness.pe',
        'https://admin.probusiness.pe',
        'https://qaintranet.probusiness.pe',
        'https://intranetback.probusiness.pe',
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:8000',
    ],

    // ✅ También permitir cualquier subdominio de probusiness.pe
    'allowed_origins_patterns' => [
        '#^https?://.*\.probusiness\.pe(:\d+)?$#',
        '#^http://localhost(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
