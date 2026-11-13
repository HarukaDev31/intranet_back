<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorAbono;

/**
 * Reparte un abono entre proveedores sin pasar el saldo de cada uno (RN-50, RN-51, RN-55).
 */
class PagoProveedorAbonoEvaluador
{
    /**
     * @param  array<int, float>  $aplicaciones  id de proveedor => monto
     * @param  array<int, float>  $saldos
     * @return array{aplicado: float, excedente: float, cubiertos: array<int, int>, aplicaciones: array<int, float>}
     */
    public function evaluar(float $importe, array $aplicaciones, array $saldos): array
    {
        if ($importe <= 0) {
            throw new PagoProveedorException('El importe recibido debe ser mayor a 0.');
        }

        $aplicado = 0.0;
        $cubiertos = [];
        $normalizadas = [];

        foreach ($aplicaciones as $id => $monto) {
            $monto = round((float) $monto, 2);
            if ($monto < 0) {
                throw new PagoProveedorException('El monto aplicado no puede ser negativo.');
            }
            if ($monto == 0.0) {
                continue;
            }
            $saldo = round((float) ($saldos[$id] ?? 0), 2);
            if (!array_key_exists($id, $saldos)) {
                throw new PagoProveedorException('Hay un proveedor que no pertenece a esta solicitud.');
            }
            if ($monto - $saldo > 0.009) {
                throw new PagoProveedorException('No se puede aplicar más que el saldo pendiente del proveedor.');
            }
            $normalizadas[$id] = $monto;
            $aplicado += $monto;
            if ($monto + 0.009 >= $saldo && $saldo > 0) {
                $cubiertos[] = (int) $id;
            }
        }

        $aplicado = round($aplicado, 2);
        if ($aplicado <= 0) {
            throw new PagoProveedorException('El abono no cubre a ningún proveedor.');
        }
        if ($aplicado - $importe > 0.009) {
            throw new PagoProveedorException('La suma aplicada supera el importe recibido.');
        }

        $excedente = round($importe - $aplicado, 2);
        $siguePendiente = false;
        foreach ($normalizadas as $id => $monto) {
            if ($monto + 0.009 < round((float) $saldos[$id], 2)) {
                $siguePendiente = true;
            }
        }
        if ($excedente > 0.009 && $siguePendiente) {
            throw new PagoProveedorException('Todavía hay saldo pendiente en los proveedores elegidos. Asigna el importe o reduce lo recibido.');
        }

        return [
            'aplicado' => $aplicado,
            'excedente' => $excedente,
            'cubiertos' => $cubiertos,
            'aplicaciones' => $normalizadas,
        ];
    }

    /**
     * Al validar, el abono que registró Ventas toma el importe del banco (#1724): si al banco llegó menos de lo
     * aplicado y el voucher es de un solo proveedor, su aplicación baja al importe del banco y la diferencia queda
     * como faltante (tolerancia / Por cobrar). Un voucher compartido conserva su reparto: Ventas elige quién
     * continúa (RN-66). Si llegó de más, la aplicación no cambia y lo que sobra es excedente.
     *
     * @param  array<int, float>  $aplicaciones  id de aplicación => monto aplicado por Ventas
     * @return array<int, float>  id de aplicación => monto ajustado (solo las que cambian)
     */
    public function ajusteAlBanco(float $importeBanco, array $aplicaciones): array
    {
        if (count($aplicaciones) !== 1) {
            return [];
        }
        $banco = round(max(0.0, $importeBanco), 2);
        $ajuste = [];
        foreach ($aplicaciones as $id => $monto) {
            if (round($monto, 2) - $banco >= 0.005) {
                $ajuste[$id] = $banco;
            }
        }

        return $ajuste;
    }

    public function decisionExcedente(float $excedente, bool $hayCuota2, float $tolerance = 1.0): ?string
    {
        if ($excedente <= 0) {
            return null;
        }
        $tolerance = max(0.0, $tolerance);
        // RN-122: ≤ tolerancia → cuota 2 si existe; si no, no se devuelve.
        if ($excedente <= $tolerance + 0.001) {
            return $hayCuota2 ? PagoProveedorAbono::EXCESS_APPLIED : PagoProveedorAbono::EXCESS_KEEP;
        }
        // RN-123: > tolerancia sin cuotas → reembolso directo; con cuotas → consultar (72 h).
        return $hayCuota2 ? PagoProveedorAbono::EXCESS_TO_CONSULT : PagoProveedorAbono::EXCESS_REFUND;
    }
}
