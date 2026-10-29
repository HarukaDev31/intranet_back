<?php

namespace App\Services\PagoProveedor;

/**
 * Texto de vista previa de WhatsApp (RN-131, RN-134, RN-07).
 * No incluye tipo de cambio ni código de solicitud.
 */
class PagoProveedorMensajeService
{
    /**
     * @param  array{proveedores: array<int, array<string, mixed>>, total: float, total_ahora: float}  $calculo
     * @param  array<int, array<string, mixed>>  $detalleProveedores  código, nombre, producto, alineados por orden
     */
    public function resumen(string $nombreCliente, array $calculo, array $detalleProveedores): string
    {
        $nombre = trim($nombreCliente) !== '' ? trim($nombreCliente) : 'cliente';
        $lineasCalculo = $calculo['proveedores'];
        $hayCuotas = $this->hayCuotas($lineasCalculo);
        $rotuloTotal = $hayCuotas ? 'Total a pagar ahora' : 'Total a pagar';
        $monto = $hayCuotas ? $calculo['total_ahora'] : $calculo['total'];

        $encabezado = 'Hola, ' . $nombre . " 🙋🏻‍♀️\n|";

        if (count($lineasCalculo) >= 2) {
            return $encabezado . "\n"
                . 'Tu pago a proveedor incluye ' . count($lineasCalculo) . " proveedores.\n"
                . "El detalle está en el PDF de resumen.\n"
                . '💰 ' . $rotuloTotal . ': $' . $this->monto($monto);
        }

        $linea = $lineasCalculo[0] ?? null;
        $detalle = $detalleProveedores[0] ?? [];
        $codigo = trim((string) ($detalle['code_supplier'] ?? ''));
        $nombreProveedor = trim((string) ($detalle['nombre_proveedor'] ?? ''));
        $producto = trim((string) ($detalle['producto'] ?? ''));
        $segunda = $nombreProveedor !== '' ? $nombreProveedor . ' · ' . $producto : $producto;

        $texto = $encabezado . "\n";
        if ($codigo !== '') {
            $texto .= 'Proveedor 1: ' . $codigo . "\n";
        }
        if ($segunda !== '') {
            $texto .= $segunda . "\n";
        }

        if ($linea) {
            if (($linea['etiqueta_bancaria'] ?? null) !== null) {
                $texto .= '➡ Comisión bancaria: ' . $linea['etiqueta_bancaria'] . "\n";
            } elseif ((float) $linea['bancaria'] > 0) {
                $texto .= '➡ Comisión bancaria: $' . $this->monto((float) $linea['bancaria']) . "\n";
            }
            if ((float) $linea['administrativa'] > 0) {
                $texto .= '➡ Comisión administrativa: $' . $this->monto((float) $linea['administrativa']) . "\n";
            }
        }

        $texto .= '💰 ' . $rotuloTotal . ': $' . $this->monto($monto);

        return $texto;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     */
    private function hayCuotas(array $lineas): bool
    {
        foreach ($lineas as $linea) {
            if (($linea['modalidad_abono'] ?? 'total') === 'fraccionado') {
                return true;
            }
        }

        return false;
    }

    private function monto(float $monto): string
    {
        return number_format($monto, 2, '.', ',');
    }
}
