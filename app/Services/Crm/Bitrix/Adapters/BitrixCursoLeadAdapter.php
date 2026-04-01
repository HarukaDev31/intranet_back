<?php

namespace App\Services\Crm\Bitrix\Adapters;

use App\Contracts\Crm\LandingLeadCrmSyncAdapterInterface;
use App\Services\Crm\Bitrix\BitrixCrmClient;
use App\Services\Crm\Bitrix\BitrixCursoSyncSupport;
use App\Support\Phone\PeruPhoneFormatter;

/**
 * Embudo Curso de Importación en Bitrix (CATEGORY_ID 9 en el ejemplo).
 * Campos UF, pauta y stage se configuran en config/landing_curso.php.
 */
class BitrixCursoLeadAdapter implements LandingLeadCrmSyncAdapterInterface
{
    /** @var BitrixCrmClient */
    private $client;

    public function __construct(BitrixCrmClient $client)
    {
        $this->client = $client;
    }

    public function funnelKey(): string
    {
        return 'curso';
    }

    public function sync(array $leadRow): array
    {
        $cfg = config('landing_curso.bitrix', []);

        if (!($cfg['enabled'] ?? true)) {
            return ['skipped' => true, 'reason' => 'bitrix_disabled'];
        }

        $stageId = trim((string) ($cfg['deal_stage_id'] ?? ''));
        if ($stageId === '') {
            return ['skipped' => true, 'reason' => 'deal_stage_id_not_configured'];
        }

        $parsed = BitrixCursoSyncSupport::parseName($leadRow['nombre'] ?? '');
        $phone = PeruPhoneFormatter::toE164((string) ($leadRow['whatsapp'] ?? ''));
        if ($phone === '') {
            throw new \InvalidArgumentException('WhatsApp vacío o inválido para CRM.');
        }

        $sonetContact = !empty($cfg['contact_register_sonet']) ? ['REGISTER_SONET_EVENT' => 'Y'] : [];
        $sonetDeal = !empty($cfg['deal_register_sonet']) ? ['REGISTER_SONET_EVENT' => 'Y'] : [];

        $contactId = $this->client->findContactIdByPhone($phone);
        $contactCreated = false;

        if (!$contactId) {
            $contactFields = [
                'NAME' => $parsed['name'],
                'LAST_NAME' => $parsed['last_name'],
                'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'MOBILE']],
                'SOURCE_ID' => $cfg['contact_source_id'] ?? 'WEB',
                'SOURCE_DESCRIPTION' => $cfg['contact_source_description'] ?? 'Landing Curso',
                'UTM_CAMPAIGN' => (string) ($leadRow['codigo_campana'] ?? ''),
                'COMMENTS' => $this->buildContactComments($leadRow),
            ];
            $email = trim((string) ($leadRow['email'] ?? ''));
            if ($email !== '') {
                $contactFields['EMAIL'] = [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']];
            }
            $contactId = $this->client->createContact($contactFields, $sonetContact);
            $contactCreated = true;
        }

        $dealFields = $this->buildDealFields($leadRow, $contactId, $cfg);
        $dealId = $this->client->createDeal($dealFields, $sonetDeal);

        return [
            'contact_id' => $contactId,
            'deal_id' => $dealId,
            'contact_created' => $contactCreated,
        ];
    }

    /**
     * @param  array<string, mixed>  $leadRow
     * @param  array<string, mixed>  $cfg
     */
    private function buildDealFields(array $leadRow, $contactId, array $cfg)
    {
        $nombre = (string) ($leadRow['nombre'] ?? 'Sin nombre');
        $titleTemplate = (string) ($cfg['deal_title_template'] ?? ':nombre - Landing Curso');
        $title = strtr($titleTemplate, [':nombre' => $nombre]);

        $sourceDescTemplate = (string) ($cfg['deal_source_description_template'] ?? 'Landing Curso - :campana');
        $sourceDescription = strtr($sourceDescTemplate, [
            ':campana' => (string) ($leadRow['codigo_campana'] ?? ''),
        ]);

        $categoryId = (int) ($cfg['deal_category_id'] ?? 9);

        $fields = [
            'TITLE' => $title,
            'CATEGORY_ID' => $categoryId,
            'STAGE_ID' => (string) ($cfg['deal_stage_id'] ?? ''),
            'SOURCE_ID' => $cfg['deal_source_id'] ?? 'WEB',
            'SOURCE_DESCRIPTION' => $sourceDescription,
            'CONTACT_ID' => $contactId,
            'CURRENCY_ID' => (string) ($cfg['deal_currency_id'] ?? 'PEN'),
            'OPENED' => $cfg['deal_opened'] ?? 'Y',
            'COMMENTS' => $this->buildDealComments($leadRow),
            'UTM_CAMPAIGN' => (string) ($leadRow['codigo_campana'] ?? ''),
        ];

        $ufFecha = trim((string) ($cfg['uf_fecha_registro'] ?? ''));
        if ($ufFecha !== '') {
            $fields[$ufFecha] = BitrixCursoSyncSupport::registrationDateYmd($leadRow);
        }

        $ufServicios = trim((string) ($cfg['uf_servicios'] ?? ''));
        $ufServiciosVal = $cfg['uf_servicios_value'] ?? null;
        if ($ufServicios !== '' && $ufServiciosVal !== null && $ufServiciosVal !== '') {
            $fields[$ufServicios] = (string) $ufServiciosVal;
        }

        $ufPauta = trim((string) ($cfg['uf_pauta'] ?? ''));
        if ($ufPauta !== '') {
            $pautaId = BitrixCursoSyncSupport::mapPauta(
                (string) ($leadRow['codigo_campana'] ?? ''),
                isset($cfg['pauta_rules']) && is_array($cfg['pauta_rules']) ? $cfg['pauta_rules'] : []
            );
            if ($pautaId !== null) {
                $fields[$ufPauta] = $pautaId;
            }
        }

        $extraUf = isset($cfg['deal_uf_extra']) && is_array($cfg['deal_uf_extra']) ? $cfg['deal_uf_extra'] : [];
        foreach ($extraUf as $ufKey => $value) {
            $k = trim((string) $ufKey);
            if ($k !== '' && $value !== null && $value !== '') {
                $fields[$k] = $value;
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $leadRow
     */
    private function buildContactComments(array $leadRow)
    {
        $lines = array_filter([
            'Experiencia importando: ' . ($leadRow['experiencia_importando'] ?? ''),
            trim((string) ($leadRow['codigo_campana'] ?? '')) !== '' ? 'Campaña: ' . trim((string) $leadRow['codigo_campana']) : null,
        ]);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $leadRow
     */
    private function buildDealComments(array $leadRow)
    {
        $created = $leadRow['created_at'] ?? null;
        $createdStr = $created instanceof \DateTimeInterface
            ? $created->format('Y-m-d H:i:s')
            : (string) $created;

        return implode("\n", array_filter([
            'Email: ' . ($leadRow['email'] ?? ''),
            'Experiencia importando: ' . ($leadRow['experiencia_importando'] ?? ''),
            trim((string) ($leadRow['codigo_campana'] ?? '')) !== '' ? 'Campaña: ' . trim((string) $leadRow['codigo_campana']) : null,
            isset($leadRow['ip_address']) && $leadRow['ip_address'] !== '' ? 'IP: ' . $leadRow['ip_address'] : null,
            $createdStr !== '' ? 'Registrado: ' . $createdStr : 'Registrado: ' . now()->toDateTimeString(),
        ]));
    }
}
