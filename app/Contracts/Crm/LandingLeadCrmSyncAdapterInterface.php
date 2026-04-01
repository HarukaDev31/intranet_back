<?php

namespace App\Contracts\Crm;

/**
 * Adaptador CRM por embudo de landing (consolidado, curso, etc.).
 * Cada implementación define mapeo a contacto/deal del proveedor (Bitrix, HubSpot, …).
 */
interface LandingLeadCrmSyncAdapterInterface
{
    /**
     * Identificador del embudo (debe coincidir con el usado al despachar el job).
     */
    public function funnelKey(): string;

    /**
     * @param  array<string, mixed>  $leadRow  Atributos del modelo (p. ej. toArray())
     * @return array<string, mixed>  Metadatos devueltos por el CRM (contact_id, deal_id, …)
     */
    public function sync(array $leadRow): array;
}
