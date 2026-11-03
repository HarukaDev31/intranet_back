<?php

namespace App\Services\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorEjecucion;
use App\Models\PagoProveedor\PagoProveedorMensaje;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;

/**
 * Texto de vista previa de WhatsApp (RN-131, RN-133, RN-134, RN-135, RN-93).
 * No incluye tipo de cambio ni código de solicitud. El envío real espera la plantilla de Meta.
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
                . '💰 ' . $rotuloTotal . ': $' . $this->amount($monto);
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
                $texto .= '➡ Comisión bancaria: $' . $this->amount((float) $linea['bancaria']) . "\n";
            }
            if ((float) $linea['administrativa'] > 0) {
                $texto .= '➡ Comisión administrativa: $' . $this->amount((float) $linea['administrativa']) . "\n";
            }
        }

        $texto .= '💰 ' . $rotuloTotal . ': $' . $this->amount($monto);

        return $texto;
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function usdNoticeText(PagoProveedorSolicitudProveedor $provider, float $amount): string
    {
        $provider->loadMissing('solicitud');
        $request = $provider->solicitud;
        $name = trim((string) ($provider->supplier_name ?? ''));
        $who = $name !== '' ? $provider->code_supplier.' · '.$name : (string) $provider->code_supplier;

        return "Hola, ".($request?->client_name ?: 'cliente')." 🙋🏻‍♀️\n|\n"
            ."¡Te confirmamos que el pago a tu proveedor se realizó exitosamente! ✅\n"
            .$who."\n".$provider->product."\n"
            .'Consolidado: '.($request?->consolidation ?: '—')."\n"
            .'Monto pagado: $'.$this->amount($amount)."\n"
            .'La constancia quedó registrada en el sistema.';
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function storeUsdNotice(PagoProveedorSolicitudProveedor $provider, float $amount, array $actor): void
    {
        if ($this->alreadyStored($provider, 'aviso_usd')) {
            return;
        }
        $this->store($provider, 'aviso_usd', $this->usdNoticeText($provider, $amount), $actor);
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function rmbNoticeText(PagoProveedorSolicitudProveedor $provider): string
    {
        $provider->loadMissing('solicitud');
        $request = $provider->solicitud;
        $name = trim((string) ($provider->supplier_name ?? ''));
        $providerLine = $name !== '' ? "• Proveedor: ".$name."\n" : '';

        return "Hola, ".($request?->client_name ?: 'cliente')." 🙋🏻‍♀️\n|\n"
            ."¡Te confirmamos que el pago a tu proveedor se realizó exitosamente! ✅\n"
            ."• Código de proveedor: ".$provider->code_supplier."\n"
            .$providerLine
            ."• Producto: ".$provider->product."\n"
            .'• N.° de consolidado: '.($request?->consolidation ?: '—')."\n\n"
            .'Por favor, confirma la recepción con tu proveedor.';
    }

    /**
     * El aviso se guarda antes de registrar el pago (RN-93). El texto no lleva importe ni código de solicitud.
     *
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function confirmRmbNotice(PagoProveedorSolicitudProveedor $provider, float $yuanPaid, array $actor): void
    {
        $provider->loadMissing('solicitud');
        $type = $this->rmbNoticeType($yuanPaid);
        if ($this->alreadyStored($provider, $type)) {
            return;
        }
        $this->store($provider, $type, $this->rmbNoticeText($provider), $actor);
    }

    public function rmbNoticeMatches(PagoProveedorSolicitudProveedor $provider, float $yuanPaid): bool
    {
        return $this->alreadyStored($provider, $this->rmbNoticeType($yuanPaid));
    }

    public function storeRmbNotice(PagoProveedorSolicitudProveedor $provider, array $actor): void
    {
        $this->confirmRmbNotice($provider, 0, $actor);
    }

    private function rmbNoticeType(float $yuanPaid): string
    {
        return 'aviso_rmb:'.number_format(round($yuanPaid, 2), 2, '.', '');
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function balanceStored(PagoProveedorSolicitudProveedor $provider): bool
    {
        return $this->alreadyStored($provider, 'balance');
    }

    public function storeBalance(PagoProveedorSolicitudProveedor $provider, array $actor): void
    {
        if ($this->alreadyStored($provider, 'balance')) {
            return;
        }
        $provider->loadMissing(['solicitud', 'ejecuciones']);
        $request = $provider->solicitud;
        $name = trim((string) ($provider->supplier_name ?? ''));
        $who = $name !== '' ? $provider->code_supplier.' · '.$name : (string) $provider->code_supplier;
        $lines = [$who, (string) $provider->product];
        foreach ($provider->ejecuciones as $execution) {
            if (!in_array($execution->exchange_result, [PagoProveedorEjecucion::RESULT_COLLECT, PagoProveedorEjecucion::RESULT_REFUND], true)) {
                continue;
            }
            $label = $execution->exchange_result === PagoProveedorEjecucion::RESULT_COLLECT ? 'Diferencia a pagar' : 'Diferencia a devolver';
            $lines[] = 'Pago '.$execution->supplier_payment.': '.$label.' $'.$this->amount(abs((float) $execution->exchange_difference_usd));
        }
        if (count($lines) < 3) {
            return;
        }
        $text = "Hola, ".($request?->client_name ?: 'cliente')." 🙋🏻‍♀️\n|\n".implode("\n", $lines);
        $this->store($provider, 'balance', $text, $actor);
    }

    public function noticeStored(PagoProveedorSolicitudProveedor $provider): bool
    {
        if ($provider->currency === 'USD') {
            return $this->alreadyStored($provider, 'aviso_usd');
        }

        return PagoProveedorMensaje::query()
            ->where('request_id', $provider->request_id)
            ->where('message_type', 'like', 'aviso_rmb%')
            ->where('body', 'like', '%'.$provider->code_supplier.'%')
            ->exists();
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    private function store(PagoProveedorSolicitudProveedor $provider, string $type, string $text, array $actor): void
    {
        PagoProveedorMensaje::query()->create([
            'request_id' => $provider->request_id,
            'message_type' => $type,
            'recipient' => $provider->solicitud?->client_whatsapp,
            'body' => $text,
            'user_id' => $actor['id'],
            'created_at' => now(),
        ]);
    }

    private function alreadyStored(PagoProveedorSolicitudProveedor $provider, string $type): bool
    {
        return PagoProveedorMensaje::query()
            ->where('request_id', $provider->request_id)
            ->where('message_type', $type)
            ->where('body', 'like', '%'.$provider->code_supplier.'%')
            ->exists();
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

    private function amount(float $monto): string
    {
        return number_format($monto, 2, '.', ',');
    }
}
