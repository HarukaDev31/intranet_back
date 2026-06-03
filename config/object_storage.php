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

    /*
    |--------------------------------------------------------------------------
    | CDN público (CloudFront, etc.)
    |--------------------------------------------------------------------------
    | URLs de API / storage legacy → https://cdn.probusiness.pe/...
    */
    'cdn_base_url' => rtrim((string) env('OBJECT_STORAGE_CDN_URL', env('AWS_CDN_URL', '')), '/'),

    /** Si true, la URL CDN incluye AWS_UPLOAD_PREFIX antes de la ruta relativa de BD */
    'cdn_include_s3_prefix' => env('OBJECT_STORAGE_CDN_INCLUDE_PREFIX', true),

    /** Usar CDN cuando FILESYSTEM_UPLOAD_DISK=s3 (aunque el objeto aún esté solo en disco legacy) */
    'cdn_when_upload_disk_s3' => env('OBJECT_STORAGE_CDN_WHEN_S3', true),

];
