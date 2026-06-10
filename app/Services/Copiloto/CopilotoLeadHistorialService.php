<?php

namespace App\Services\Copiloto;

use App\Helpers\UserLookupHelper;
use App\Models\CargaConsolidada\Cotizacion;
use Carbon\Carbon;

/**
 * Historial comercial del lead (cotizaciones carga consolidada) por teléfono.
 */
class CopilotoLeadHistorialService
{
    /**
     * @param  string  $phoneE164
     * @return array<string, mixed>
     */
    public function buildForPhone($phoneE164)
    {
        $phoneDigits = $this->normalizePhoneDigits($phoneE164);
        if ($phoneDigits === '') {
            return $this->emptyPayload();
        }

        $phoneSin51 = preg_replace('/^51/', '', $phoneDigits);
        $user = UserLookupHelper::findUserByContact(null, $phoneE164, null);

        $query = Cotizacion::query()
            ->with(['contenedor'])
            ->whereNull('deleted_at')
            ->whereNull('id_cliente_importacion');

        $query->where(function ($q) use ($phoneDigits, $phoneSin51, $user) {
            $this->applyTelefonoMatch($q, $phoneDigits, $phoneSin51);

            if ($user) {
                if (!empty($user->email)) {
                    $q->orWhere('correo', (string) $user->email);
                }
                if (!empty($user->dni)) {
                    $q->orWhere('documento', (string) $user->dni);
                }
            }
        });

        $cotizaciones = $query
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        if ($cotizaciones->isEmpty()) {
            return $this->emptyPayload();
        }

        $rows = [];
        $volumeSum = 0.0;
        $volumeCount = 0;
        $investmentSum = 0.0;

        foreach ($cotizaciones as $cot) {
            $volumen = $this->resolveVolumen($cot);
            $monto = $this->resolveMonto($cot);

            if ($volumen !== null && $volumen > 0) {
                $volumeSum += $volumen;
                $volumeCount++;
            }
            if ($monto !== null && $monto > 0) {
                $investmentSum += $monto;
            }

            $rows[] = [
                'id' => (int) $cot->id,
                'f' => $this->formatFechaCorta($cot->fecha),
                'r' => $this->formatRutaEstado($cot),
                'c' => $volumen !== null && $volumen > 0
                    ? number_format($volumen, 1, '.', '') . ' CBM'
                    : '—',
                'p' => $monto !== null && $monto > 0
                    ? 'S/ ' . number_format($monto, 0, '.', ',')
                    : '—',
                'estado_cotizador' => $cot->estado_cotizador,
                'estado_cliente' => $cot->estado_cliente,
            ];
        }

        $cbmPromedio = $volumeCount > 0 ? round($volumeSum / $volumeCount, 1) : null;

        return [
            'historial' => $rows,
            'cotizaciones_count' => count($rows),
            'cbm' => $cbmPromedio !== null ? number_format($cbmPromedio, 1, '.', '') . ' CBM' : null,
            'inversion' => $investmentSum > 0
                ? 'S/ ' . number_format($investmentSum, 0, '.', ',')
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyPayload()
    {
        return [
            'historial' => [],
            'cotizaciones_count' => 0,
            'cbm' => null,
            'inversion' => null,
        ];
    }

    /**
     * @param  mixed  $phone
     * @return string
     */
    protected function normalizePhoneDigits($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 9) {
            $digits = '51' . $digits;
        }

        return $digits;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $q
     * @param  string  $phoneDigits
     * @param  string  $phoneSin51
     */
    protected function applyTelefonoMatch($q, $phoneDigits, $phoneSin51)
    {
        $telNorm = 'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, " ", ""), "-", ""), "(", ""), ")", ""), "+", "")';
        $q->where(function ($sub) use ($telNorm, $phoneDigits, $phoneSin51) {
            $sub->whereRaw("({$telNorm} LIKE ? OR {$telNorm} LIKE ? OR {$telNorm} LIKE ?)", [
                '%' . $phoneDigits . '%',
                '%' . $phoneSin51 . '%',
                '%51' . $phoneSin51 . '%',
            ]);
        });
    }

    /**
     * @param  Cotizacion  $cot
     * @return float|null
     */
    protected function resolveVolumen(Cotizacion $cot)
    {
        $v = $cot->volumen_final !== null && (float) $cot->volumen_final > 0
            ? (float) $cot->volumen_final
            : ($cot->volumen !== null ? (float) $cot->volumen : null);

        return $v !== null && $v > 0 ? $v : null;
    }

    /**
     * @param  Cotizacion  $cot
     * @return float|null
     */
    protected function resolveMonto(Cotizacion $cot)
    {
        $m = $cot->monto_final !== null && (float) $cot->monto_final > 0
            ? (float) $cot->monto_final
            : ($cot->monto !== null ? (float) $cot->monto : null);

        return $m !== null && $m > 0 ? $m : null;
    }

    /**
     * @param  mixed  $fecha
     * @return string
     */
    protected function formatFechaCorta($fecha)
    {
        if ($fecha === null || $fecha === '') {
            return '—';
        }
        try {
            return Carbon::parse($fecha)->locale('es')->isoFormat('MMM YY');
        } catch (\Exception $e) {
            return '—';
        }
    }

    /**
     * @param  Cotizacion  $cot
     * @return string
     */
    protected function formatRutaEstado(Cotizacion $cot)
    {
        $parts = [];
        $contenedor = $cot->contenedor;
        if ($contenedor && trim((string) $contenedor->carga) !== '') {
            $parts[] = trim((string) $contenedor->carga);
        }
        $estado = trim((string) ($cot->estado_cotizador ?: $cot->estado ?: ''));
        if ($estado !== '') {
            $parts[] = $estado;
        }

        return count($parts) ? implode(' · ', $parts) : 'Cotización';
    }
}
