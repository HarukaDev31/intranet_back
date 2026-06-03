<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disco para nuevas subidas
    |--------------------------------------------------------------------------
    | Valores: local | public | s3
    | Cuando es s3, las rutas guardadas en BD siguen siendo relativas (ej. cargaconsolidada/...).
    */
    'upload_disk' => env('FILESYSTEM_UPLOAD_DISK', env('FILESYSTEM_DRIVER', 'local')),

    /*
    |--------------------------------------------------------------------------
    | Discos legacy (lectura de archivos ya guardados en servidor)
    |--------------------------------------------------------------------------
    */
    'legacy_disk' => env('FILESYSTEM_LEGACY_DISK', 'local'),
    'legacy_public_disk' => env('FILESYSTEM_LEGACY_PUBLIC_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | S3
    |--------------------------------------------------------------------------
    */
    'signed_url_minutes' => (int) env('AWS_SIGNED_URL_MINUTES', 120),

    /** Si true, serve* redirige a URL firmada en lugar de hacer stream por PHP */
    'serve_via_signed_redirect' => env('AWS_SERVE_VIA_SIGNED_REDIRECT', true),

    /** Prefijo dentro del bucket (ej. probusiness/production) — también en filesystems.disks.s3.root */
    's3_prefix' => env('AWS_UPLOAD_PREFIX', ''),

];
