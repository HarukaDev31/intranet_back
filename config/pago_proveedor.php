<?php

$front = env('PAGO_PROVEEDOR_FRONT_URL', env('FRONTEND_URL'));
if (!$front) {
    $front = str_contains((string) env('APP_URL', ''), 'qa-intranetback')
        ? 'https://qaintranet.probusiness.pe'
        : 'http://localhost:3000';
}

// Links al cliente (formulario T-T): en QA apuntan al deploy beta del front de clientes (rama qa); en prod, clientes.probusiness.pe.
$clientes = env('APP_URL_CLIENTES');
if (!$clientes) {
    $clientes = str_contains((string) env('APP_URL', ''), 'qa-intranetback')
        ? 'https://qa--nimble-gumption-e62d9f.netlify.app'
        : 'https://clientes.probusiness.pe';
}

return [
    'front_url' => rtrim((string) $front, '/'),
    'clientes_url' => rtrim((string) $clientes, '/'),
];
