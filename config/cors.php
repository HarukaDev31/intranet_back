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

    // ✅ Usar patrón para subdominios en lugar de *
    'allowed_origins' => [
        // Dejamos vacío porque nginx lo maneja
        // pero incluimos localhost para desarrollo
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:8000',
    ],

    'allowed_origins_patterns' => [
        // Permite CUALQUIER subdominio de probusiness.pe
        '#^https?://(.*\.)?probusiness\.pe(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
