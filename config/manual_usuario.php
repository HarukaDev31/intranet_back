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
    | Disco y carpeta para uploads del CMS (storage/app/manual/...)
    */
    'storage_disk' => 'local',
    'storage_dir' => 'manual',

    /*
    | Ruta al front Nuxt (para php artisan manual:scan-front-widgets).
    | Por defecto intenta ../probusiness_intranetv3 relativo al back.
    */
    'front_path' => env('MANUAL_USUARIO_FRONT_PATH', ''),
];
