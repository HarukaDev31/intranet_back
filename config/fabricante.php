<?php

return [
    'session_ttl_days' => (int) env('FABRICANTE_SESSION_TTL_DAYS', 30),
    'verification_token_ttl_hours' => (int) env('FABRICANTE_VERIFICATION_TTL_HOURS', 48),

    'firebase_web_api_key' => env('FIREBASE_WEB_API_KEY'),

    'verification_url' => env(
        'FABRICANTE_EMAIL_VERIFICATION_URL',
        env('APP_URL') . '/fabricante/verificar-email'
    ),
];
