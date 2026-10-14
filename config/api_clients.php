<?php

/*
|--------------------------------------------------------------------------
| API de integración para otras organizaciones
|--------------------------------------------------------------------------
| Cada "API client" pertenece a UNA organización, se autentica con un token
| (guardado solo como hash), solo puede usar los scopes que se le dieron y
| solo ve/modifica datos de su organización. Todo queda en api_client_audit_logs.
*/
return [
    // Scopes disponibles: "scope" => descripción.
    'scopes' => [
        'cotizaciones-resumen:create' => 'Crear cotizaciones resumen (JSON + PDF de respaldo)',
        'cotizaciones-resumen:read' => 'Listar cotizaciones resumen de su organización',
        'proveedores:update-code' => 'Actualizar el code_supplier de proveedores de su organización',
        'cotizaciones-resumen:update-state' => 'Cambiar el estado (COTIZADO/CONFIRMADO) de cotizaciones resumen de su organización',
        'cotizaciones-resumen:delete' => 'Eliminar (soft delete) cotizaciones resumen en estado COTIZADO de su organización',
    ],

    // Límites por defecto al crear un cliente (editables por cliente).
    'default_rate_limit_per_minute' => (int) env('API_CLIENT_DEFAULT_RPM', 60),
    'default_daily_quota' => (int) env('API_CLIENT_DEFAULT_DAILY_QUOTA', 2000),
    'max_rate_limit_per_minute' => 600,
    'max_daily_quota' => 100000,

    // Intentos fallidos de autenticación permitidos por IP y minuto (anti fuerza bruta).
    'failed_auth_per_minute' => (int) env('API_CLIENT_FAILED_AUTH_PER_MINUTE', 20),

    // PDF de respaldo de "crear cotización resumen".
    'pdf_max_kb' => 10240,
    'payload_max_bytes' => 262144, // 256 KB de JSON

    // Retención de la auditoría (días); 0 = sin limpieza automática.
    'audit_retention_days' => (int) env('API_CLIENT_AUDIT_RETENTION_DAYS', 180),

    'grupo_integracion' => 'Integracion',
];
