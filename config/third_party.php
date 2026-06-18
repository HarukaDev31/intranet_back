<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Token de acceso de solo lectura para integraciones externas
    |--------------------------------------------------------------------------
    |
    | Se envía como Authorization: Bearer {THIRD_PARTY_READ_ONLY_TOKEN}
    |
    */
    'read_only_token' => env('THIRD_PARTY_READ_ONLY_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Rate limit del endpoint JSON de cotizaciones (peticiones por minuto / IP)
    |--------------------------------------------------------------------------
    */
    'cotizacion_export_rate_limit' => (int) env('THIRD_PARTY_COTIZACION_EXPORT_RATE_LIMIT', 5),
];
