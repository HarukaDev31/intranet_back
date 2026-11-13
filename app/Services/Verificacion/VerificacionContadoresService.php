<?php

namespace App\Services\Verificacion;

use App\Models\CargaConsolidada\Pago;
use App\Models\CargaConsolidada\PagoBoletinQuimico;
use App\Models\CargaConsolidada\PagoConcept;
use App\Models\CargaConsolidada\TramiteAduanaPago;
use App\Models\PedidoCursoPago;
use App\Models\Viatico;
use App\Services\PagoProveedor\PagoProveedorAprobacionService;
use App\Services\PagoProveedor\PagoProveedorEjecucionService;
use App\Services\PagoProveedor\PagoProveedorReembolsoService;
use App\Services\PagoProveedor\PagoProveedorVerificacionService;

/**
 * Conteos baratos (COUNT) de lo pendiente de verificación / aprobación.
 * Una sola respuesta para sidebar, tabs y home de Administración.
 */
class VerificacionContadoresService
{
    public function all()
    {
        $deposits = app(PagoProveedorVerificacionService::class)->pendingCount();
        $approvals = app(PagoProveedorAprobacionService::class)->pendingCount()
            + app(PagoProveedorReembolsoService::class)->pendingCount();
        $executions = app(PagoProveedorEjecucionService::class)->pendingCount();

        $consolidado = (int) Pago::query()
            ->where('status', Pago::ESTADOS['PENDIENTE'])
            ->whereIn('id_concept', [PagoConcept::CONCEPT_PAGO_LOGISTICA, PagoConcept::CONCEPT_PAGO_IMPUESTOS])
            ->count();

        $delivery = (int) Pago::query()
            ->where('status', Pago::ESTADOS['PENDIENTE'])
            ->where('id_concept', PagoConcept::CONCEPT_PAGO_DELIVERY)
            ->count();

        $cursos = (int) PedidoCursoPago::query()
            ->where('status', 'PENDIENTE')
            ->count();

        $permisos = (int) TramiteAduanaPago::query()
            ->where('estado_administracion', 'PENDIENTE')
            ->count();

        $boletin = (int) PagoBoletinQuimico::query()
            ->where('status', PagoBoletinQuimico::STATUS_PENDIENTE)
            ->count();

        $viaticos = (int) Viatico::query()
            ->where('status', 'PENDING')
            ->count();

        $verificacionTotal = $consolidado + $cursos + $delivery + $permisos + $boletin + $deposits;

        return [
            'consolidado' => $consolidado,
            'cursos' => $cursos,
            'delivery' => $delivery,
            'permisos' => $permisos,
            'boletin_quimico' => $boletin,
            'supplier_payment' => $deposits,
            'supplier_approvals' => $approvals,
            'supplier_executions' => $executions,
            'viaticos' => $viaticos,
            'verificacion_total' => $verificacionTotal,
        ];
    }

    /**
     * @param  array  $partial  claves a devolver (si vacío, todas)
     * @return array
     */
    public function slice(array $partial = [])
    {
        $all = $this->all();
        if (empty($partial)) {
            return $all;
        }

        $out = [];
        foreach ($partial as $key) {
            if (array_key_exists($key, $all)) {
                $out[$key] = $all[$key];
            }
        }
        if (isset($out['consolidado']) || isset($out['cursos']) || isset($out['delivery'])
            || isset($out['permisos']) || isset($out['boletin_quimico']) || isset($out['supplier_payment'])) {
            $out['verificacion_total'] = $all['verificacion_total'];
        }

        return $out;
    }
}
