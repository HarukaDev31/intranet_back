<?php

namespace App\Services\PagoProveedor;

/**
 * Comisiones de la cotización y de la solicitud (RN-11 no aplica aquí; RN-30 a RN-36).
 *
 * La bancaria de varios proveedores en yuanes se cobra una sola vez, sobre la suma
 * del envío, y la lleva el primero del grupo (RN-35). En dólares cada proveedor
 * paga la suya. La administrativa no se agrupa.
 */
class PagoProveedorComisionService
{
    /**
     * @param  array<int, array<string, mixed>>  $proveedores
     * @return array{proveedores: array<int, array<string, mixed>>, total: float, total_ahora: float, comisiones: float}
     */
    public function calcular(array $proveedores, TarifaPagoProveedor $tarifa): array
    {
        $lineas = [];
        foreach (array_values($proveedores) as $indice => $proveedor) {
            $lineas[] = $this->calcularLinea($proveedor, $tarifa, $indice + 1);
        }

        $lineas = $this->agruparBancariaRmb($lineas, $tarifa);

        $total = 0.0;
        $totalAhora = 0.0;
        $comisiones = 0.0;
        foreach ($lineas as $linea) {
            $total += $linea['total'];
            $totalAhora += $linea['total_ahora'];
            $comisiones += $linea['bancaria'] + $linea['administrativa'];
        }

        return [
            'proveedores' => $lineas,
            'total' => $this->dinero($total),
            'total_ahora' => $this->dinero($totalAhora),
            'comisiones' => $this->dinero($comisiones),
        ];
    }

    /**
     * @param  array<string, mixed>  $proveedor
     * @return array<string, mixed>
     */
    private function calcularLinea(array $proveedor, TarifaPagoProveedor $tarifa, int $ordenFallback): array
    {
        $moneda = strtoupper((string) ($proveedor['moneda'] ?? 'USD'));
        if (!in_array($moneda, ['USD', 'RMB'], true)) {
            $moneda = 'USD';
        }

        $abono = ($proveedor['modalidad_abono'] ?? 'total') === 'fraccionado' ? 'fraccionado' : 'total';
        $pago = ($proveedor['modalidad_pago'] ?? 'total') === 'fraccionado' ? 'fraccionado' : 'total';
        if ($moneda === 'USD') {
            $pago = $abono;
        }

        $valor = $this->dinero((float) ($proveedor['valor_carga_usd'] ?? 0));
        $porcentajes = $this->porcentajes($abono === 'fraccionado' || $pago === 'fraccionado', $proveedor);
        $cobraBancaria = (bool) ($proveedor['comision_bancaria'] ?? true);
        $cobraAdmin = (bool) ($proveedor['comision_administrativa'] ?? false);
        $bancariaPorCuota = $abono === 'fraccionado' && $pago === 'fraccionado';
        $adminPorCuota = $bancariaPorCuota;
        $adminDoble = $moneda === 'RMB' && $abono === 'total' && $pago === 'fraccionado' && $cobraAdmin;

        $cuotasCliente = $abono === 'fraccionado' ? 2 : 1;
        $cuotas = [];
        for ($i = 0; $i < $cuotasCliente; $i++) {
            $porcentaje = $cuotasCliente === 1 ? 100.0 : $porcentajes[$i];
            $carga = $cuotasCliente === 1 ? $valor : $this->dinero($valor * $porcentaje / 100);
            $cuotas[] = [
                'numero' => $i + 1,
                'porcentaje' => $porcentaje,
                'carga' => $carga,
                'bancaria' => 0.0,
                'administrativa' => 0.0,
                'total' => $carga,
            ];
        }

        $bancaria = 0.0;
        if ($cobraBancaria && $valor > 0) {
            if ($bancariaPorCuota) {
                foreach ($cuotas as $i => $cuota) {
                    $cuotas[$i]['bancaria'] = $tarifa->bancaria($moneda, $cuota['carga']);
                    $bancaria += $cuotas[$i]['bancaria'];
                }
            } else {
                $bancaria = $tarifa->bancaria($moneda, $valor);
                $cuotas[0]['bancaria'] = $bancaria;
            }
        }

        $administrativa = 0.0;
        if ($cobraAdmin) {
            if ($adminDoble) {
                $administrativa = $this->dinero($tarifa->administrativa * 2);
                $cuotas[0]['administrativa'] = $administrativa;
            } elseif ($adminPorCuota) {
                foreach ($cuotas as $i => $cuota) {
                    $cuotas[$i]['administrativa'] = $tarifa->administrativa;
                    $administrativa += $tarifa->administrativa;
                }
            } else {
                $administrativa = $tarifa->administrativa;
                $cuotas[0]['administrativa'] = $administrativa;
            }
        }

        foreach ($cuotas as $i => $cuota) {
            $cuotas[$i]['bancaria'] = $this->dinero($cuota['bancaria']);
            $cuotas[$i]['administrativa'] = $this->dinero($cuota['administrativa']);
            $cuotas[$i]['total'] = $this->dinero($cuota['carga'] + $cuotas[$i]['bancaria'] + $cuotas[$i]['administrativa']);
        }

        $pagos = [];
        $pagosN = $pago === 'fraccionado' ? 2 : 1;
        for ($i = 0; $i < $pagosN; $i++) {
            $porcentaje = $pagosN === 1 ? 100.0 : $porcentajes[$i];
            $pagos[] = [
                'numero' => $i + 1,
                'porcentaje' => $porcentaje,
                'carga' => $pagosN === 1 ? $valor : $this->dinero($valor * $porcentaje / 100),
            ];
        }

        $total = 0.0;
        foreach ($cuotas as $cuota) {
            $total += $cuota['total'];
        }

        return [
            'orden' => (int) ($proveedor['orden'] ?? $ordenFallback),
            'moneda' => $moneda,
            'modalidad_abono' => $abono,
            'modalidad_pago' => $pago,
            'valor_carga_usd' => $valor,
            'comision_bancaria' => $cobraBancaria,
            'comision_administrativa' => $cobraAdmin,
            'bancaria_por_cuota' => $bancariaPorCuota,
            'cuotas' => $cuotas,
            'pagos_proveedor' => $pagos,
            'bancaria' => $this->dinero($bancaria),
            'administrativa' => $this->dinero($administrativa),
            'bancaria_incluida_en' => null,
            'etiqueta_bancaria' => null,
            'total' => $this->dinero($total),
            'total_ahora' => $this->dinero($cuotas[0]['total']),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     * @return array<int, array<string, mixed>>
     */
    private function agruparBancariaRmb(array $lineas, TarifaPagoProveedor $tarifa): array
    {
        $indices = [];
        foreach ($lineas as $i => $linea) {
            if ($linea['moneda'] === 'RMB' && $linea['comision_bancaria'] && $linea['valor_carga_usd'] > 0) {
                $indices[] = $i;
            }
        }

        if (count($indices) < 2) {
            return $lineas;
        }

        $modo = $lineas[$indices[0]]['bancaria_por_cuota'];
        foreach ($indices as $i) {
            if ($lineas[$i]['bancaria_por_cuota'] !== $modo) {
                return $lineas;
            }
        }

        $carrier = $indices[0];
        $ordenCarrier = $lineas[$carrier]['orden'];

        if ($modo) {
            $nCuotas = count($lineas[$carrier]['cuotas']);
            for ($c = 0; $c < $nCuotas; $c++) {
                $base = 0.0;
                foreach ($indices as $i) {
                    $base += $lineas[$i]['cuotas'][$c]['carga'] ?? 0;
                }
                $fee = $tarifa->bancaria('RMB', $base);
                foreach ($indices as $i) {
                    $propia = $i === $carrier ? $fee : 0.0;
                    $lineas[$i]['cuotas'][$c]['bancaria'] = $this->dinero($propia);
                    $lineas[$i]['cuotas'][$c]['total'] = $this->dinero(
                        $lineas[$i]['cuotas'][$c]['carga']
                        + $lineas[$i]['cuotas'][$c]['bancaria']
                        + $lineas[$i]['cuotas'][$c]['administrativa']
                    );
                }
            }
        } else {
            $base = 0.0;
            foreach ($indices as $i) {
                $base += $lineas[$i]['valor_carga_usd'];
            }
            $fee = $tarifa->bancaria('RMB', $base);
            foreach ($indices as $i) {
                $propia = $i === $carrier ? $fee : 0.0;
                $lineas[$i]['cuotas'][0]['bancaria'] = $this->dinero($propia);
                if (isset($lineas[$i]['cuotas'][1])) {
                    $lineas[$i]['cuotas'][1]['bancaria'] = 0.0;
                }
                foreach ($lineas[$i]['cuotas'] as $c => $cuota) {
                    $lineas[$i]['cuotas'][$c]['total'] = $this->dinero(
                        $cuota['carga'] + $lineas[$i]['cuotas'][$c]['bancaria'] + $cuota['administrativa']
                    );
                }
            }
        }

        foreach ($indices as $i) {
            $bancaria = 0.0;
            $total = 0.0;
            foreach ($lineas[$i]['cuotas'] as $cuota) {
                $bancaria += $cuota['bancaria'];
                $total += $cuota['total'];
            }
            $lineas[$i]['bancaria'] = $this->dinero($bancaria);
            $lineas[$i]['total'] = $this->dinero($total);
            $lineas[$i]['total_ahora'] = $this->dinero($lineas[$i]['cuotas'][0]['total']);
            if ($i !== $carrier) {
                $lineas[$i]['bancaria_incluida_en'] = $ordenCarrier;
                $lineas[$i]['etiqueta_bancaria'] = 'Incluida en el Proveedor ' . $ordenCarrier;
            }
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $proveedor
     * @return array{0: float, 1: float}
     */
    private function porcentajes(bool $fraccionado, array $proveedor): array
    {
        if (!$fraccionado) {
            return [100.0, 0.0];
        }

        $p1 = (float) ($proveedor['porcentaje_1'] ?? 50);
        $p2 = (float) ($proveedor['porcentaje_2'] ?? (100 - $p1));

        return [$p1, $p2];
    }

    private function dinero(float $monto): float
    {
        return round($monto, 2);
    }
}
