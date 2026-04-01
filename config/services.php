<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Bitrix24 (webhook entrante). Si BITRIX_WEBHOOK_URL está vacío, no se encolan jobs CRM.
    | Ejemplo: https://tudominio.bitrix24.com/rest/1/tu_codigo_webhook/
    */
    'bitrix' => [
        'webhook_url' => env('BITRIX_WEBHOOK_URL'),
        'timeout' => (int) env('BITRIX_HTTP_TIMEOUT', 30),
        'queue' => env('BITRIX_QUEUE', 'default'),
    ],

];
