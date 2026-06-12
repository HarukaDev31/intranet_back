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
 * Requiere f_inicio (excluye importados en otro flujo).
 */
class SeguimientoConsolidadoVincularEligibility
{
    private const ANIO_INICIO = 2026;

    private const MIN_CARGA_ANIO_INICIO = 11;

    /**
     * @param Contenedor $contenedor
     * @return bool
     */
    public static function tieneFInicio(Contenedor $contenedor)
    {
        return !empty($contenedor->f_inicio);
    }

    /**
     * @return string
     */
    public static function mensajeSinFInicio()
    {
        return 'Consolidado sin f_inicio (importado en otro flujo); no aplica Excel seguimiento Drive.';
    }

    /**
     * @param Contenedor $contenedor
     * @return int
     */
    public static function resolveAnioContenedor(Contenedor $contenedor)
    {
        if (empty($contenedor->f_inicio)) {
            return 0;
        }

        return (int) Carbon::parse($contenedor->f_inicio)->format('Y');
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
        if (!self::tieneFInicio($contenedor)) {
            return false;
        }

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
        if (!self::tieneFInicio($contenedor)) {
            return false;
        }

        if (!empty($contenedor->excel_seguimiento_drive_link)) {
            return false;
        }

        if (ExcelSeguimientoLinkStatus::isProcessing($contenedor->excel_seguimiento_link_status)) {
            return false;
        }

        return self::cumpleUmbralCarga($contenedor);
    }

    /**
     * Vincular, regenerar y sync automático (requiere f_inicio + umbral de carga).
     *
     * @param Contenedor $contenedor
     * @return bool
     */
    public static function puedeOperarSeguimientoDrive(Contenedor $contenedor)
    {
        return self::tieneFInicio($contenedor) && self::cumpleUmbralCarga($contenedor);
    }

    /**
     * @return Collection<int, Contenedor>
     */
    public static function contenedoresPendientesVincular()
    {
        $candidatos = Contenedor::query()
            ->whereNotNull('carga')
            ->where('carga', '!=', '')
            ->whereNotNull('f_inicio')
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
            ->whereYear('f_inicio', '>=', self::ANIO_INICIO)
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
        if (!self::tieneFInicio($contenedor)) {
            return 'Sin f_inicio (importado; excluido de seguimiento Drive)';
        }

        $anio = self::resolveAnioContenedor($contenedor);
        $min = self::minCargaParaAnio($anio);
        $num = self::resolveNumeroCarga($contenedor);

        if ($min === null) {
            return sprintf('Fuera de alcance (desde #%d-%d)', self::MIN_CARGA_ANIO_INICIO, self::ANIO_INICIO);
        }

        return sprintf('#%d-%d (mínimo #%d)', $num, $anio, $min);
    }
}
