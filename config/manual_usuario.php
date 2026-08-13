<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Manual de usuario (contenido en resources/manual)
    |--------------------------------------------------------------------------
    */
    'base_path' => resource_path('manual'),

    /*
    | Grupos excluidos del menú sidebar / seed (clientes externos, etc.)
    */
    'excluded_grupo_ids' => [
        1205, // Cliente
    ],

    /*
    | Usuario root (ve todos los roles + PDF global)
    */
    'root_usuario' => 'root',

    /*
    | Disco legacy (solo lectura de uploads antiguos en storage/app).
    | Las subidas nuevas usan Object Storage (FILESYSTEM_UPLOAD_DISK / S3 + CDN).
    */
    'storage_disk' => env('MANUAL_USUARIO_STORAGE_DISK', 'local'),
    'storage_dir' => env('MANUAL_USUARIO_STORAGE_DIR', 'manual'),

    /*
    | Ruta al front Nuxt (para php artisan manual:scan-front-widgets).
    | Por defecto intenta ../probusiness_intranetv3 relativo al back.
    */
    'front_path' => env('MANUAL_USUARIO_FRONT_PATH', ''),
];
