<?php

namespace App\Services\Verificacion;

use App\Models\CargaConsolidada\Pago;
use App\Models\CargaConsolidada\PagoBoletinQuimico;
use App\Models\CargaConsolidada\PagoConcept;
use App\Models\CargaConsolidada\TramiteAduanaPago;
use App\Models\PedidoCursoPago;
use App\Models\Viatico;

/**
 * Conteos baratos (COUNT) de lo pendiente de verificacion / viaticos.
 * Una sola respuesta para sidebar, tabs y home de Administracion.
 */
class VerificacionContadoresService
{
    public function all()
    {
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

        $verificacionTotal = $consolidado + $cursos + $delivery + $permisos + $boletin;

        return [
            'consolidado' => $consolidado,
            'cursos' => $cursos,
            'delivery' => $delivery,
            'permisos' => $permisos,
            'boletin_quimico' => $boletin,
            'supplier_payment' => 0,
            'supplier_approvals' => 0,
            'supplier_executions' => 0,
            'viaticos' => $viaticos,
            'verificacion_total' => $verificacionTotal,
        ];
    }

    /**
     * @param  array  $partial  claves a devolver (si vacio, todas)
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
