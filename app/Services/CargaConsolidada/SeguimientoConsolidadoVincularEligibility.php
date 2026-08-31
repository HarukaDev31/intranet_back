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
 * Aplica con estado_china PENDIENTE o RECIBIENDO (no se exige RECIBIENDO).
 * Se detiene el sync cuando estado_finanzas deja de ser PENDIENTE.
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

        if (!self::estaEstadoFinanzasPendiente($contenedor)) {
            return false;
        }

        if (!self::estaEstadoChinaHabilitadoParaSeguimiento($contenedor)) {
            return false;
        }

        if (self::tieneExcelDrivePropio($contenedor)) {
            return false;
        }

        if (ExcelSeguimientoLinkStatus::isProcessing($contenedor->excel_seguimiento_link_status)) {
            return false;
        }

        return self::cumpleUmbralCarga($contenedor);
    }

    /**
     * True si este consolidado tiene un Excel en Drive que no comparte con otro (p. ej. clone al partir).
     *
     * @param Contenedor $contenedor
     * @return bool
     */
    public static function tieneExcelDrivePropio(Contenedor $contenedor)
    {
        $fileId = trim((string) ($contenedor->excel_seguimiento_drive_file_id ?? ''));
        $link = trim((string) ($contenedor->excel_seguimiento_drive_link ?? ''));

        if ($fileId === '' && $link === '') {
            return false;
        }

        if ($fileId === '') {
            return true;
        }

        return !Contenedor::query()
            ->where('id', '!=', $contenedor->id)
            ->where('excel_seguimiento_drive_file_id', $fileId)
            ->exists();
    }

    /**
     * Solo consolidados con estado_finanzas = PENDIENTE siguen en sync Excel.
     *
     * @param Contenedor $contenedor
     * @return bool
     */
    public static function estaEstadoFinanzasPendiente(Contenedor $contenedor)
    {
        return strtoupper(trim((string) ($contenedor->estado_finanzas ?? ''))) === Contenedor::CONTEDOR_PENDIENTE;
    }

    /**
     * China operativa para Excel Drive: PENDIENTE (nuevo consolidado) o RECIBIENDO.
     * Vacío se trata como PENDIENTE. COMPLETADO ya no crea ni sincroniza.
     *
     * @param Contenedor $contenedor
     * @return bool
     */
    public static function estaEstadoChinaHabilitadoParaSeguimiento(Contenedor $contenedor)
    {
        $estado = strtoupper(trim((string) ($contenedor->estado_china ?? '')));

        if ($estado === '') {
            return true;
        }

        return $estado !== Contenedor::CONTEDOR_CERRADO;
    }

    /**
     * Vincular, regenerar y sync automático (f_inicio + umbral + finanzas PENDIENTE + china no COMPLETADO).
     *
     * @param Contenedor $contenedor
     * @return bool
     */
    public static function puedeOperarSeguimientoDrive(Contenedor $contenedor)
    {
        return self::tieneFInicio($contenedor)
            && self::cumpleUmbralCarga($contenedor)
            && self::estaEstadoFinanzasPendiente($contenedor)
            && self::estaEstadoChinaHabilitadoParaSeguimiento($contenedor);
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
            ->where('estado_finanzas', Contenedor::CONTEDOR_PENDIENTE)
            ->where(function ($q) {
                $q->whereNull('estado_china')
                    ->orWhere('estado_china', '')
                    ->orWhere('estado_china', '!=', Contenedor::CONTEDOR_CERRADO);
            })
            ->where(function ($q) {
                $q->whereNull('excel_seguimiento_drive_link')
                    ->orWhere('excel_seguimiento_drive_link', '')
                    ->orWhereIn('excel_seguimiento_drive_file_id', function ($sub) {
                        $sub->select('excel_seguimiento_drive_file_id')
                            ->from('carga_consolidada_contenedor')
                            ->whereNotNull('excel_seguimiento_drive_file_id')
                            ->where('excel_seguimiento_drive_file_id', '!=', '')
                            ->groupBy('excel_seguimiento_drive_file_id')
                            ->havingRaw('COUNT(*) > 1');
                    });
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

        if (!self::estaEstadoFinanzasPendiente($contenedor)) {
            return sprintf(
                'Estado finanzas "%s" (solo se sincroniza en PENDIENTE)',
                (string) ($contenedor->estado_finanzas ?? '')
            );
        }

        if (!self::estaEstadoChinaHabilitadoParaSeguimiento($contenedor)) {
            return sprintf(
                'Estado China "%s" (PENDIENTE y RECIBIENDO sí aplican; COMPLETADO no)',
                (string) ($contenedor->estado_china ?? '')
            );
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
