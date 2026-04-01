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

    /*
    | Mapeo embudo consolidado → negocio Bitrix (deal / contacto).
    | Ajusta STAGE_ID y CATEGORY_ID según tu embudo en Bitrix24.
    */
    'bitrix' => [
        'deal_category_id' => (int) env('BITRIX_CONSOLIDADO_DEAL_CATEGORY_ID', 0),
        'deal_stage_id' => env('BITRIX_CONSOLIDADO_DEAL_STAGE_ID', 'UC_NF1ZJG'),
        'deal_currency_id' => env('BITRIX_CONSOLIDADO_DEAL_CURRENCY', 'PEN'),
        'deal_opened' => env('BITRIX_CONSOLIDADO_DEAL_OPENED', 'Y'),
        'deal_source_id' => env('BITRIX_CONSOLIDADO_DEAL_SOURCE_ID', 'WEB'),
        'deal_title_template' => env('BITRIX_CONSOLIDADO_DEAL_TITLE', ':nombre - Landing Web'),
        'deal_source_description_template' => env('BITRIX_CONSOLIDADO_DEAL_SOURCE_DESC', 'Landing Consolidado - :campana'),
        'contact_source_id' => env('BITRIX_CONSOLIDADO_CONTACT_SOURCE_ID', 'WEB'),
        'contact_source_description' => env('BITRIX_CONSOLIDADO_CONTACT_SOURCE_DESC', 'Landing Consolidado'),
    ],

];
