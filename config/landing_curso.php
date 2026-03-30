<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token Bearer para el formulario de la landing curso
    |--------------------------------------------------------------------------
    |
    | Debe coincidir con PUBLIC_LANDING_CURSO_FORM_TOKEN en el front Astro.
    | Generar con: php -r "echo bin2hex(random_bytes(32));"
    |
    */

    'form_token' => env('LANDING_CURSO_FORM_TOKEN'),

];
