<?php

namespace App\Console\Commands;

use App\Models\ApiClientAuditLog;
use Illuminate\Console\Command;

/**
 * Limpia la auditoría de la API de integración más antigua que la retención configurada.
 */
class PurgeApiClientAuditLogs extends Command
{
    protected $signature = 'api-clients:purge-audit {--days= : Días a conservar (por defecto config api_clients.audit_retention_days)}';

    protected $description = 'Elimina registros antiguos de api_client_audit_logs';

    public function handle(): int
    {
        $dias = $this->option('days') !== null ? (int) $this->option('days') : (int) config('api_clients.audit_retention_days', 180);
        if ($dias <= 0) {
            $this->info('Retención deshabilitada (0 días): no se borra nada.');

            return 0;
        }

        $total = ApiClientAuditLog::query()->where('created_at', '<', now()->subDays($dias))->delete();
        $this->info("Auditoría purgada: {$total} registros con más de {$dias} días.");

        return 0;
    }
}
