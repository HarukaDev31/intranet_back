<?php

namespace App\Services\Crm;

use App\Contracts\Crm\LandingLeadCrmSyncAdapterInterface;
use App\Services\Crm\Bitrix\Adapters\BitrixConsolidadoLeadAdapter;
use App\Services\Crm\Bitrix\Adapters\BitrixCursoLeadAdapter;
use App\Services\Crm\Bitrix\BitrixCrmClient;
use InvalidArgumentException;

/**
 * Resuelve el adaptador CRM según el embudo y construye el cliente Bitrix si aplica.
 */
class LandingLeadCrmSyncResolver
{
    public function resolve(string $funnel): ?LandingLeadCrmSyncAdapterInterface
    {
        $client = BitrixCrmClient::fromConfig();
        if ($client === null) {
            return null;
        }

        switch ($funnel) {
            case 'consolidado':
                return new BitrixConsolidadoLeadAdapter($client);
            case 'curso':
                return new BitrixCursoLeadAdapter($client);
            default:
                throw new InvalidArgumentException("Embudo CRM desconocido: {$funnel}");
        }
    }
}
