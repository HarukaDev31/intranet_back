<?php

namespace App\Services\CargaConsolidada;

use App\Enums\CargaConsolidada\ExcelSeguimientoLinkStatus;
use App\Models\CargaConsolidada\Contenedor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Auto-vinculación Excel seguimiento → Drive.
 *
 * Regla: consolidados #11-2026 en adelante (2026 ≥11; años posteriores desde #1).
 * Año: YEAR(f_inicio). Número: campo carga.
 */
class SeguimientoConsolidadoVincularEligibility
{
    private const ANIO_INICIO = 2026;

    private const MIN_CARGA_ANIO_INICIO = 11;

    /**
     * @param Contenedor $contenedor
     * @return int
     */
    public static function resolveAnioContenedor(Contenedor $contenedor)
    {
        if (!empty($contenedor->f_inicio)) {
            return (int) Carbon::parse($contenedor->f_inicio)->format('Y');
        }

        return (int) date('Y');
    }

    /**
     * @param Contenedor $contenedor
     * @return int
     */
    public static function resolveNumeroCarga(Contenedor $contenedor)
    {
        return (int) preg_replace('/\D/', '', (string) $contenedor->carga);
    }

    /**
     * @param int $anio
     * @return int|null
     */
    public static function minCargaParaAnio($anio)
    {
        $anio = (int) $anio;

        if ($anio < self::ANIO_INICIO) {
            return null;
        }

        if ($anio === self::ANIO_INICIO) {
            return self::MIN_CARGA_ANIO_INICIO;
        }

        return 1;
    }

    /**
     * @param Contenedor $contenedor
     * @return bool
     */
    public static function cumpleUmbralCarga(Contenedor $contenedor)
    {
        if (trim((string) $contenedor->carga) === '') {
            return false;
        }

        $anio = self::resolveAnioContenedor($contenedor);
        $min = self::minCargaParaAnio($anio);

        if ($min === null) {
            return false;
        }

        return self::resolveNumeroCarga($contenedor) >= $min;
    }

    /**
     * @param Contenedor $contenedor
     * @return bool
     */
    public static function puedeVincular(Contenedor $contenedor)
    {
        if (!empty($contenedor->excel_seguimiento_drive_link)) {
            return false;
        }

        if (ExcelSeguimientoLinkStatus::isProcessing($contenedor->excel_seguimiento_link_status)) {
            return false;
        }

        return self::cumpleUmbralCarga($contenedor);
    }

    /**
     * @return Collection<int, Contenedor>
     */
    public static function contenedoresPendientesVincular()
    {
        $candidatos = Contenedor::query()
            ->whereNotNull('carga')
            ->where('carga', '!=', '')
            ->where(function ($q) {
                $q->whereNull('excel_seguimiento_drive_link')
                    ->orWhere('excel_seguimiento_drive_link', '');
            })
            ->where(function ($q) {
                $q->whereNull('excel_seguimiento_link_status')
                    ->orWhereNotIn('excel_seguimiento_link_status', [
                        ExcelSeguimientoLinkStatus::QUEUED,
                        ExcelSeguimientoLinkStatus::PROCESSING,
                    ]);
            })
            ->where(function ($q) {
                $q->whereYear('f_inicio', '>=', self::ANIO_INICIO)
                    ->orWhereNull('f_inicio');
            })
            ->orderByRaw('YEAR(f_inicio) ASC, CAST(carga AS UNSIGNED) ASC')
            ->get();

        return $candidatos->filter(function (Contenedor $contenedor) {
            return self::puedeVincular($contenedor);
        })->values();
    }

    /**
     * @param Contenedor $contenedor
     * @return string
     */
    public static function describeRegla(Contenedor $contenedor)
    {
        $anio = self::resolveAnioContenedor($contenedor);
        $min = self::minCargaParaAnio($anio);
        $num = self::resolveNumeroCarga($contenedor);

        if ($min === null) {
            return sprintf('Fuera de alcance (desde #%d-%d)', self::MIN_CARGA_ANIO_INICIO, self::ANIO_INICIO);
        }

        return sprintf('#%d-%d (mínimo #%d)', $num, $anio, $min);
    }
}
