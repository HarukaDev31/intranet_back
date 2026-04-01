<?php

namespace App\Services\Crm\Bitrix\Adapters;

use App\Contracts\Crm\LandingLeadCrmSyncAdapterInterface;
use App\Services\Crm\Bitrix\BitrixCrmClient;
use App\Support\Phone\PeruPhoneFormatter;

class BitrixConsolidadoLeadAdapter implements LandingLeadCrmSyncAdapterInterface
{
    /** @var BitrixCrmClient */
    private $client;

    public function __construct(BitrixCrmClient $client)
    {
        $this->client = $client;
    }

    public function funnelKey(): string
    {
        return 'consolidado';
    }

    public function sync(array $leadRow): array
    {
        $cfg = config('landing_consolidado.bitrix', []);

        $nameParts = explode(' ', trim((string) ($leadRow['nombre'] ?? '')), 2);
        $name = $nameParts[0] ?: 'Lead';
        $lastName = $nameParts[1] ?? '';

        $phone = PeruPhoneFormatter::toE164((string) ($leadRow['whatsapp'] ?? ''));
        if ($phone === '') {
            throw new \InvalidArgumentException('WhatsApp vacío o inválido para CRM.');
        }

        $contactId = $this->client->findContactIdByPhone($phone);
        if (!$contactId) {
            $contactId = $this->client->createContact([
                'NAME' => $name,
                'LAST_NAME' => $lastName,
                'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'MOBILE']],
                'SOURCE_ID' => $cfg['contact_source_id'] ?? 'WEB',
                'SOURCE_DESCRIPTION' => $cfg['contact_source_description'] ?? 'Landing Consolidado',
                'UTM_CAMPAIGN' => $leadRow['codigo_campana'] ?? '',
                'COMMENTS' => 'Proveedor: ' . ($leadRow['proveedor'] ?? ''),
            ]);
        }

        $dealTitleTemplate = $cfg['deal_title_template'] ?? ':nombre - Landing Web';
        $dealTitle = strtr($dealTitleTemplate, [
            ':nombre' => (string) ($leadRow['nombre'] ?? ''),
        ]);

        $dealSourceDescription = $cfg['deal_source_description_template'] ?? 'Landing Consolidado - :campana';
        $dealSourceDescription = strtr($dealSourceDescription, [
            ':campana' => (string) ($leadRow['codigo_campana'] ?? ''),
        ]);

        $dealId = $this->client->createDeal([
            'TITLE' => $dealTitle,
            'CATEGORY_ID' => (int) ($cfg['deal_category_id'] ?? 0),
            'STAGE_ID' => (string) ($cfg['deal_stage_id'] ?? 'UC_NF1ZJG'),
            'SOURCE_ID' => $cfg['deal_source_id'] ?? 'WEB',
            'SOURCE_DESCRIPTION' => $dealSourceDescription,
            'CONTACT_ID' => $contactId,
            'CURRENCY_ID' => (string) ($cfg['deal_currency_id'] ?? 'PEN'),
            'OPENED' => $cfg['deal_opened'] ?? 'Y',
            'COMMENTS' => implode("\n", array_filter([
                'Proveedor: ' . ($leadRow['proveedor'] ?? ''),
                'IP: ' . ($leadRow['ip_address'] ?? ''),
                'Campaña: ' . ($leadRow['codigo_campana'] ?? ''),
            ])),
        ]);

        return [
            'contact_id' => $contactId,
            'deal_id' => $dealId,
        ];
    }
}
