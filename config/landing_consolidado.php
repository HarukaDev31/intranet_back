<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token Bearer para el formulario de la landing consolidado
    |--------------------------------------------------------------------------
    |
    | Debe coincidir con PUBLIC_LANDING_CONSOLIDADO_FORM_TOKEN en el front Astro.
    | Generar con: php -r "echo bin2hex(random_bytes(32));"
    |
    */

    'form_token' => env('LANDING_CONSOLIDADO_FORM_TOKEN'),

];
