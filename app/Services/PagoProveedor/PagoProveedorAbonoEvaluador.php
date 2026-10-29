<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;

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

    public function decisionExcedente(float $excedente, bool $hayCuota2): ?string
    {
        if ($excedente <= 0) {
            return null;
        }
        if ($excedente <= 1) {
            return $hayCuota2 ? 'aplicado_cuota' : 'no_devuelve';
        }

        return 'por_consultar';
    }
}
