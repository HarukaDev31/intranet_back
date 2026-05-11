<?php

namespace App\Console\Commands;

use App\Services\CargaConsolidada\PromoteInspeccionadoToReservadoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Solo flujo calculadora + cotización inicial: pagos LOGÍSTICA vs {@see \App\Models\CalculadoraImportacion::logistica} (no logistica_final).
 */
class PromoteInspeccionadoToReservadoCommand extends Command
{
    protected $signature = 'carga-consolidada:promote-inspeccionados-reservados-pagos';

    protected $description = 'Calculadora/cotiz. inicial: INSPECCIONADO→RESERVADO si pagos LOGÍSTICA cubren calculadora.logistica (o monto si no hay calculadora)';

    public function handle(PromoteInspeccionadoToReservadoService $service): int
    {
        $query = DB::table('contenedor_consolidado_cotizacion_proveedores as cp')
            ->join('contenedor_consolidado_cotizacion as cc', 'cc.id', '=', 'cp.id_cotizacion')
            ->where('cc.from_calculator', 1)
            ->where(function ($q) {
                $q->whereNull('cc.cotizacion_final_url')
                    ->orWhere('cc.cotizacion_final_url', '=', '');
            })
            ->whereNotNull('cc.cotizacion_file_url')
            ->where('cc.cotizacion_file_url', '!=', '')
            ->whereRaw("UPPER(TRIM(COALESCE(cp.estados, ''))) = 'INSPECCIONADO'");

        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'deleted_at')) {
            $query->whereNull('cc.deleted_at');
        }

        $ids = $query->distinct()->orderBy('cp.id_cotizacion')->pluck('cp.id_cotizacion');

        $promotedRows = 0;
        $cotizacionesConCambio = 0;

        foreach ($ids as $idCotizacion) {
            $n = $service->promoteIfEligible((int) $idCotizacion);
            if ($n > 0) {
                $promotedRows += $n;
                $cotizacionesConCambio++;
            }
        }

        $this->info(sprintf(
            'Cotizaciones revisadas: %d | Con promoción: %d | Proveedores actualizados: %d',
            $ids->count(),
            $cotizacionesConCambio,
            $promotedRows
        ));

        return 0;
    }
}
