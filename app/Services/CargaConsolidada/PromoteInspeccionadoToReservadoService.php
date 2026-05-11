<?php

namespace App\Services\CargaConsolidada;

use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Pago;
use App\Models\CargaConsolidada\PagoConcept;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * INSPECCIONADO→RESERVADO solo en flujo calculadora + cotización inicial (Excel en cotizacion_file_url),
 * no en cotización final (cotizacion_final_url / módulo Cotización Final).
 *
 * Estado proveedor vs estado cliente (`estado_cliente`) son independientes: la promoción no usa estado_cliente.
 * No promueve si el contenedor asociado (`id_contenedor`) tiene `estado_china` = COMPLETADO (ya embarcado / cerrado).
 *
 * @see \App\Http\Controllers\CargaConsolidada\PagosController::syncEstadoCotizacionFromPayments (allí sigue LOGISTICA+IMPUESTOS; aquí solo logística)
 */
class PromoteInspeccionadoToReservadoService
{
    /**
     * Cotización vinculada a calculadora y aún sin pasar a flujo de cotización final.
     */
    public function isCalculatorInitialCotizacion(?Cotizacion $cot): bool
    {
        if (!$cot || !$cot->from_calculator) {
            return false;
        }
        if (trim((string) ($cot->cotizacion_final_url ?? '')) !== '') {
            return false;
        }
        if (trim((string) ($cot->cotizacion_file_url ?? '')) === '') {
            return false;
        }

        return true;
    }

    /**
     * Completitud solo de logística: suma de pagos concepto LOGISTICA ≥ monto logística de la calculadora (`CalculadoraImportacion.logistica`, celda J41 / cotización inicial).
     * No usa `logistica_final` (cotización final). Sin fila calculadora vinculada, último recurso: `cotizacion.monto`.
     * No incluye impuestos en la suma de pagos ni servicios_extra en el objetivo.
     *
     * Comparación en centavos (enteros) tras redondear a 2 decimales para evitar drift float.
     *
     * Solo aplica si {@see isCalculatorInitialCotizacion}.
     */
    public function isCoordinationPaymentComplete(int $idCotizacion): bool
    {
        $cot = Cotizacion::query()->with('calculadoraImportacion')->find($idCotizacion);
        if (!$this->isCalculatorInitialCotizacion($cot)) {
            return false;
        }

        $calc = $cot->calculadoraImportacion;
        if ($calc === null) {
            $montoLogistica = (float) ($cot->monto ?? 0);
        } else {
            $montoLogistica = (float) ($calc->logistica ?? 0);
            if (round($montoLogistica, 2) == 0.0) {
                return false;
            }
        }

        $dueCents = (int) round($montoLogistica * 100);
        if ($dueCents <= 0) {
            return false;
        }

        $totalPagadoLogistica = (float) Pago::query()
            ->where('id_cotizacion', $idCotizacion)
            ->where('id_concept', PagoConcept::CONCEPT_PAGO_LOGISTICA)
            ->sum('monto');

        $paidCents = (int) round($totalPagadoLogistica * 100);

        return $paidCents >= $dueCents;
    }

    /**
     * Solo si hay contenedor asociado: bloquear cuando `carga_consolidada_contenedor.estado_china` es COMPLETADO (ya embarcado).
     * Sin `id_contenedor` o sin fila contenedor no se bloquea por este criterio.
     */
    public function contenedorAsociadoPermitePromoverAReservado(?Cotizacion $cot): bool
    {
        if (!$cot || !$cot->id_contenedor) {
            return true;
        }

        $contenedor = $cot->relationLoaded('contenedor')
            ? $cot->getRelation('contenedor')
            : $cot->contenedor()->first();

        if (!$contenedor) {
            return true;
        }

        $estado = strtoupper(trim((string) ($contenedor->estado_china ?? '')));

        return $estado !== strtoupper(Contenedor::CONTEDOR_CERRADO);
    }

    /**
     * @return int Cantidad de filas de proveedor actualizadas
     */
    public function promoteIfEligible(int $idCotizacion): int
    {
        $cot = Cotizacion::query()->with('contenedor')->find($idCotizacion);
        if (!$this->isCalculatorInitialCotizacion($cot)) {
            return 0;
        }

        if (!$this->isCoordinationPaymentComplete($idCotizacion)) {
            return 0;
        }

        if (!$this->contenedorAsociadoPermitePromoverAReservado($cot)) {
            Log::info('[Pagos][Calculadora inicial] INSPECCIONADO→RESERVADO omitido: contenedor.estado_china=COMPLETADO (ya embarcado)', [
                'id_cotizacion' => $idCotizacion,
                'id_contenedor' => $cot->id_contenedor,
            ]);

            return 0;
        }

        $updated = DB::table('contenedor_consolidado_cotizacion_proveedores')
            ->where('id_cotizacion', $idCotizacion)
            ->whereRaw("UPPER(TRIM(COALESCE(estados, ''))) = 'INSPECCIONADO'")
            ->update(['estados' => 'RESERVADO']);

        if ($updated > 0) {
            Log::info('[Pagos][Calculadora inicial] INSPECCIONADO→RESERVADO por pago LOGÍSTICA completo', [
                'id_cotizacion' => $idCotizacion,
                'proveedores_actualizados' => $updated,
            ]);
        }

        return $updated;
    }
}
