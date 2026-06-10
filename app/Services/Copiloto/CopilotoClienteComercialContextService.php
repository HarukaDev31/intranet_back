<?php

namespace App\Services\Copiloto;

use App\Models\CalculadoraImportacion;
use App\Services\CalculadoraImportacion\ClienteWhatsappLookupService;
use App\Services\CalculadoraImportacionService;
use Illuminate\Support\Facades\Cache;

/**
 * Perfil comercial del lead (tipo cliente, tarifas calculadora, historial) para prompts IA.
 */
class CopilotoClienteComercialContextService
{
    /** @var ClienteWhatsappLookupService */
    protected $clienteLookup;

    /** @var CalculadoraImportacionService */
    protected $calculadoraService;

    /** @var CopilotoLeadHistorialService */
    protected $historialService;

    public function __construct(
        ClienteWhatsappLookupService $clienteLookup,
        CalculadoraImportacionService $calculadoraService,
        CopilotoLeadHistorialService $historialService
    ) {
        $this->clienteLookup = $clienteLookup;
        $this->calculadoraService = $calculadoraService;
        $this->historialService = $historialService;
    }

    /**
     * @param  string  $phoneE164
     * @return string
     */
    public function buildBlockForPhone($phoneE164)
    {
        if (!config('meta_whatsapp_copiloto.analysis_cliente_comercial_context_enabled', true)) {
            return '';
        }

        $phone = trim((string) $phoneE164);
        if ($phone === '') {
            return '';
        }

        $ttl = max(120, (int) config('meta_whatsapp_copiloto.analysis_cliente_comercial_context_cache_ttl', 600));

        return Cache::remember('wa_copiloto_cliente_comercial_' . md5($phone), $ttl, function () use ($phone) {
            return $this->formatBlock($this->resolveProfile($phone));
        });
    }

    /**
     * @param  string  $phone
     * @return array<string, mixed>
     */
    protected function resolveProfile($phone)
    {
        $historial = $this->historialService->buildForPhone($phone);
        $tipoCliente = $this->resolveTipoCliente($phone);
        $confirmadas = $this->countConfirmadas($historial);
        $calculadora = $this->findLatestCalculadora($phone);

        $cbm = null;
        if ($calculadora) {
            $calculadora->loadMissing('proveedores');
            $cbm = 0.0;
            foreach ($calculadora->proveedores as $proveedor) {
                $cbm += max((float) $proveedor->cbm, (float) $proveedor->maxcbm);
            }
            if ($cbm <= 0) {
                $cbm = $this->parseCbmFromHistorial($historial['cbm'] ?? null);
            }
        } else {
            $cbm = $this->parseCbmFromHistorial($historial['cbm'] ?? null);
        }

        $cbm = $cbm !== null && $cbm > 0 ? round($cbm, 2) : null;

        $tarifaTipo = $cbm !== null
            ? $this->calculadoraService->getTarifaPrincipalPorTipoYCbm($tipoCliente, $cbm)
            : null;
        $tarifaNuevo = $cbm !== null
            ? $this->calculadoraService->getTarifaPrincipalPorTipoYCbm('NUEVO', $cbm)
            : null;
        $tarifaPreferencial = $cbm !== null
            ? $this->calculadoraService->getTarifaPrincipalPorTipoYCbm($this->tarifaPreferencialLabel($tipoCliente), $cbm)
            : null;

        return [
            'tipo_cliente' => $tipoCliente,
            'cotizaciones_count' => (int) ($historial['cotizaciones_count'] ?? 0),
            'importaciones_confirmadas' => $confirmadas,
            'cbm_referencia' => $cbm,
            'calculadora' => $calculadora,
            'tarifa_tipo_usd_cbm' => $tarifaTipo,
            'tarifa_nuevo_usd_cbm' => $tarifaNuevo,
            'tarifa_preferencial_usd_cbm' => $tarifaPreferencial,
        ];
    }

    /**
     * @param  string  $phone
     * @return string
     */
    protected function resolveTipoCliente($phone)
    {
        $matches = $this->clienteLookup->searchClientesByWhatsapp($phone, 3);
        if (!empty($matches[0]['categoria'])) {
            return strtoupper(trim((string) $matches[0]['categoria']));
        }

        return strtoupper(trim((string) $this->calculadoraService->getTipoClientePorWhatsapp($phone)));
    }

    /**
     * @param  array<string, mixed>  $historial
     * @return int
     */
    protected function countConfirmadas(array $historial)
    {
        $rows = isset($historial['historial']) && is_array($historial['historial'])
            ? $historial['historial']
            : [];
        $count = 0;
        foreach ($rows as $row) {
            $estado = strtoupper(trim((string) ($row['estado_cotizador'] ?? '')));
            if ($estado === 'CONFIRMADO') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  string  $phone
     * @return CalculadoraImportacion|null
     */
    protected function findLatestCalculadora($phone)
    {
        $digits = preg_replace('/\D+/', '', $phone);
        $sin51 = preg_replace('/^51/', '', (string) $digits);

        return CalculadoraImportacion::query()
            ->with('proveedores')
            ->where(function ($q) use ($phone, $digits, $sin51) {
                $norm = 'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(whatsapp_cliente, " ", ""), "-", ""), "(", ""), ")", ""), "+", "")';
                $q->whereRaw("({$norm} LIKE ? OR {$norm} LIKE ? OR {$norm} LIKE ?)", [
                    '%' . $digits . '%',
                    '%' . $sin51 . '%',
                    '%51' . $sin51 . '%',
                ]);
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  mixed  $cbmLabel
     * @return float|null
     */
    protected function parseCbmFromHistorial($cbmLabel)
    {
        if ($cbmLabel === null || $cbmLabel === '') {
            return null;
        }
        if (preg_match('/([\d]+(?:[.,]\d+)?)/', (string) $cbmLabel, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    /**
     * @param  string  $tipoCliente
     * @return string
     */
    protected function tarifaPreferencialLabel($tipoCliente)
    {
        $tipo = strtoupper(trim((string) $tipoCliente));
        if (in_array($tipo, ['PREMIUM', 'SOCIO'], true)) {
            return 'PREMIUM';
        }
        if (in_array($tipo, ['RECURRENTE', 'ANTIGUO'], true)) {
            return 'RECURRENTE';
        }

        return 'NUEVO';
    }

    /**
     * @param  string  $tipoCliente
     * @return bool
     */
    protected function calificaTarifaPreferencial($tipoCliente)
    {
        return in_array(strtoupper(trim((string) $tipoCliente)), ['RECURRENTE', 'ANTIGUO', 'PREMIUM', 'SOCIO'], true);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return string
     */
    protected function formatBlock(array $profile)
    {
        $lines = ['PERFIL COMERCIAL DEL CLIENTE (datos reales — usar en objeciones de precio y cierre):'];

        $tipo = (string) ($profile['tipo_cliente'] ?? 'NUEVO');
        $lines[] = '- Tipo cliente (calculadora): ' . $tipo;
        $lines[] = '- Cotizaciones en historial: ' . (int) ($profile['cotizaciones_count'] ?? 0);
        $lines[] = '- Importaciones confirmadas: ' . (int) ($profile['importaciones_confirmadas'] ?? 0);

        if (!empty($profile['cbm_referencia'])) {
            $lines[] = '- CBM referencia: ' . $profile['cbm_referencia'];
        }

        /** @var CalculadoraImportacion|null $calc */
        $calc = $profile['calculadora'] ?? null;
        if ($calc) {
            $lines[] = '- Última calculadora #' . (int) $calc->id . ' (' . (string) ($calc->estado ?? '—') . ')';
            if ($calc->tarifa !== null && (float) $calc->tarifa > 0) {
                $lines[] = '- Tarifa aplicada en cotización actual: USD ' . number_format((float) $calc->tarifa, 0, '.', ',') . '/CBM';
            }
            if ($calc->tarifa_descuento !== null && (float) $calc->tarifa_descuento > 0) {
                $lines[] = '- Descuento ya registrado en calculadora: USD ' . number_format((float) $calc->tarifa_descuento, 2, '.', ',');
            }
            if (!empty($calc->tipo_cliente) && strtoupper((string) $calc->tipo_cliente) !== $tipo) {
                $lines[] = '- Tipo en calculadora guardada: ' . strtoupper((string) $calc->tipo_cliente);
            }
        }

        if (!empty($profile['tarifa_tipo_usd_cbm'])) {
            $lines[] = '- Tarifa referencial para su tipo (' . $tipo . '): USD '
                . number_format((float) $profile['tarifa_tipo_usd_cbm'], 0, '.', ',') . '/CBM';
        }
        if (!empty($profile['tarifa_nuevo_usd_cbm']) && !empty($profile['tarifa_preferencial_usd_cbm'])
            && (float) $profile['tarifa_nuevo_usd_cbm'] > (float) $profile['tarifa_preferencial_usd_cbm']) {
            $lines[] = '- Tarifa NUEVO vs preferencial: USD '
                . number_format((float) $profile['tarifa_nuevo_usd_cbm'], 0, '.', ',')
                . ' vs USD '
                . number_format((float) $profile['tarifa_preferencial_usd_cbm'], 0, '.', ',')
                . '/CBM';
        }

        if ($this->calificaTarifaPreferencial($tipo)) {
            $lines[] = '- Es cliente con historial/fidelidad: puede ofrecerse tarifa preferencial o descuento especial por antigüedad (menciónalo con naturalidad, sin inventar monto si no hay tarifa_descuento).';
        } elseif ($tipo === 'NUEVO' && (int) ($profile['importaciones_confirmadas'] ?? 0) === 0) {
            $lines[] = '- Cliente nuevo: prioriza valor del servicio; descuento solo si hay margen o autorización — sugiere revisar la cotización punto por punto.';
        } else {
            $lines[] = '- Cliente sin categoría preferencial clara: refuerza valor (aduana, permisos, Yiwu→Lima) y ofrece revisar la cotización antes de prometer descuento.';
        }

        $lines[] = 'En objeción de precio ("caro", "sale más"): combina valor + tarifa según tipo + opción de recotizar con descuento si califica; no inventes totales en soles.';

        return implode("\n", $lines);
    }
}
