<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un registro por request a la API de integración (éxito o fallo).
 */
class ApiClientAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'api_client_audit_logs';

    protected $fillable = [
        'api_client_id',
        'organizacion_id',
        'token_prefix',
        'method',
        'path',
        'scope',
        'status_code',
        'ip',
        'user_agent',
        'duration_ms',
        'error',
        'meta',
    ];

    protected $casts = [
        'api_client_id' => 'integer',
        'organizacion_id' => 'integer',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];
}
