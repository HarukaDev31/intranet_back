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
];
