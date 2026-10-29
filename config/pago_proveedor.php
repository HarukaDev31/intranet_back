<?php

$front = env('PAGO_PROVEEDOR_FRONT_URL', env('FRONTEND_URL'));
if (!$front) {
    $front = str_contains((string) env('APP_URL', ''), 'qa-intranetback')
        ? 'https://qaintranet.probusiness.pe'
        : 'http://localhost:3000';
}

return [
    'front_url' => rtrim((string) $front, '/'),
];
