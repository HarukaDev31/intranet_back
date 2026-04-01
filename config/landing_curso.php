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

    /*
    |--------------------------------------------------------------------------
    | Bitrix — embudo "Curso de Importación" (ej. CATEGORY_ID = 9)
    |--------------------------------------------------------------------------
    |
    | Con BITRIX_WEBHOOK_URL definido se encola SyncLandingLeadToCrmJob.
    | LANDING_CURSO_BITRIX_ENABLED=false desactiva solo este embudo.
    | UF_* deben existir en tu Bitrix; ajusta IDs de pauta según listas del portal.
    |
    */

    'bitrix' => [
        'enabled' => env('LANDING_CURSO_BITRIX_ENABLED') === null
            ? true
            : filter_var(env('LANDING_CURSO_BITRIX_ENABLED'), FILTER_VALIDATE_BOOLEAN),

        'deal_category_id' => (int) env('BITRIX_CURSO_DEAL_CATEGORY_ID', 9),
        'deal_stage_id' => env('BITRIX_CURSO_DEAL_STAGE_ID', 'C9:UC_33L0M2'),
        'deal_currency_id' => env('BITRIX_CURSO_DEAL_CURRENCY', 'PEN'),
        'deal_opened' => env('BITRIX_CURSO_DEAL_OPENED', 'Y'),
        'deal_source_id' => env('BITRIX_CURSO_DEAL_SOURCE_ID', 'WEB'),
        'deal_title_template' => env('BITRIX_CURSO_DEAL_TITLE', ':nombre - Landing Web'),
        'deal_source_description_template' => env(
            'BITRIX_CURSO_DEAL_SOURCE_DESC',
            'Landing Curso - :campana'
        ),

        'contact_source_id' => env('BITRIX_CURSO_CONTACT_SOURCE_ID', 'WEB'),
        'contact_source_description' => env('BITRIX_CURSO_CONTACT_SOURCE_DESC', 'Landing Curso'),

        'contact_register_sonet' => filter_var(
            env('BITRIX_CURSO_CONTACT_REGISTER_SONET', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'deal_register_sonet' => filter_var(
            env('BITRIX_CURSO_DEAL_REGISTER_SONET', true),
            FILTER_VALIDATE_BOOLEAN
        ),

        /* Campos personalizados del deal (como en BitrixCrmService de ejemplo) */
        'uf_fecha_registro' => env('BITRIX_CURSO_UF_FECHA_REGISTRO', 'UF_CRM_1718205777413'),
        'uf_servicios' => env('BITRIX_CURSO_UF_SERVICIOS', 'UF_CRM_1732648597921'),
        'uf_servicios_value' => env('BITRIX_CURSO_UF_SERVICIOS_VALUE', '173'),
        'uf_pauta' => env('BITRIX_CURSO_UF_PAUTA', 'UF_CRM_1716916721532'),

        /*
        | Reglas: si codigo_campana (minúsculas) contiene alguna needle, se envía value a uf_pauta.
        */
        'pauta_rules' => [
            ['needles' => ['fb', 'facebook'], 'value' => env('BITRIX_CURSO_PAUTA_FB', '49')],
            ['needles' => ['ig', 'instagram'], 'value' => env('BITRIX_CURSO_PAUTA_IG', '51')],
            ['needles' => ['tk', 'tiktok'], 'value' => env('BITRIX_CURSO_PAUTA_TK', '53')],
            ['needles' => ['gads', 'google'], 'value' => env('BITRIX_CURSO_PAUTA_GOOGLE', '55')],
            ['needles' => ['webinar'], 'value' => env('BITRIX_CURSO_PAUTA_WEBINAR', '57')],
        ],

        /* UF adicionales estáticos: [ 'UF_CRM_xxx' => 'valor' ] */
        'deal_uf_extra' => [],
    ],

];
