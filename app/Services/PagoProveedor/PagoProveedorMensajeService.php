<?php

namespace App\Services\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorEjecucion;
use App\Models\PagoProveedor\PagoProveedorMensaje;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;

/**
 * Texto de WhatsApp (RN-131, RN-133, RN-134, RN-135, RN-93).
 * No incluye tipo de cambio ni código de solicitud. El resumen de cotización se envía desde el número de ventas.
 */
class PagoProveedorMensajeService
{
    /**
     * Same structure as the functional mock: suppliers, payment mode, summary and "Confirmo".
     *
     * @param  array{proveedores: array<int, array<string, mixed>>, total: float, total_ahora: float}  $calculo
     * @param  array<int, array<string, mixed>>  $detalleProveedores  código, nombre, producto, alineados por orden
     */
    public function resumen(string $nombreCliente, array $calculo, array $detalleProveedores, ?string $validUntil = null, bool $forRequest = false): string
    {
        $lines = $calculo['proveedores'];
        $hasInstallments = $this->hayCuotas($lines);
        $labels = array_map(fn (array $detail) => $this->supplierLabel($detail), $detalleProveedores);
        $modes = array_map(fn (array $line) => ($line['modalidad_abono'] ?? 'total') === 'fraccionado' ? 'Fraccionado' : 'Total', $lines);
        $sameMode = count(array_unique($modes)) <= 1;

        $cargo = 0.0;
        $bankFee = 0.0;
        $adminFee = 0.0;
        foreach ($lines as $line) {
            $first = $line['cuotas'][0] ?? [];
            $cargo += (float) ($first['carga'] ?? $line['valor_carga_usd'] ?? 0);
            $bankFee += (float) ($first['bancaria'] ?? $line['bancaria'] ?? 0);
            $adminFee += (float) ($first['administrativa'] ?? $line['administrativa'] ?? 0);
        }

        $text = [
            'Hola, '.$this->firstName($nombreCliente).' 🙋🏻‍♀️',
            $forRequest ? 'Te compartimos el resumen actualizado de tu pago a proveedor 👇' : 'Te compartimos tu cotización de pago a proveedor 👇',
            '',
            count($lines) > 1 ? '*Proveedores:*' : '*Proveedor:*',
        ];
        foreach ($labels as $label) {
            $text[] = '➡️ '.$label;
        }
        $text[] = '';
        $text[] = '*Modalidad de abono (Probusiness):* '.($sameMode ? ($modes[0] ?? 'Total') : implode(' | ', $modes));
        $text[] = '';
        $text[] = '*Resumen:*';
        $text[] = '📦 Valor de carga: $'.$this->amount($cargo);
        if ($bankFee > 0) {
            $text[] = '➡️ Comisión bancaria / plataforma: $'.$this->amount($bankFee);
        }
        if ($adminFee > 0) {
            $text[] = '➡️ Comisión administrativa: $'.$this->amount($adminFee);
        }
        $text[] = '💰 *'.($hasInstallments ? 'Total a pagar ahora' : 'Total a pagar').': $'.$this->amount($hasInstallments ? $calculo['total_ahora'] : $calculo['total']).'*';
        if ($hasInstallments) {
            $text[] = '';
            $text[] = $sameMode ? '💳 Pagas en 2 cuotas: este total es la cuota 1.' : '💳 Incluye la cuota 1 de los proveedores en cuotas.';
        }
        $text[] = '';
        if ($validUntil !== null && $validUntil !== '') {
            $text[] = '⏳ *Vigencia:* hasta el '.$validUntil;
            $text[] = '';
        }
        $text[] = '📎 En el PDF adjunto verás el detalle por proveedor.';
        $text[] = '';
        $text[] = '✅ Si estás de acuerdo, respóndenos *"Confirmo"* y '.($forRequest ? 'continuamos con tu pago.' : 'registramos tu solicitud.');

        return implode("\n", $text);
    }

    public function firstName(?string $client): string
    {
        $parts = preg_split('/\s+/', trim((string) $client)) ?: [];

        return ($parts[0] ?? '') !== '' ? $parts[0] : 'cliente';
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function supplierLabel(array $detail): string
    {
        $reference = trim((string) ($detail['code_supplier'] ?? '')) ?: trim((string) ($detail['nombre_proveedor'] ?? '')) ?: 'Proveedor';
        $product = trim((string) ($detail['producto'] ?? ''));

        return $product !== '' ? $reference.' | '.$product : $reference;
    }

    /**
     * @return array<int, string>
     */
    private function supplierBullets(PagoProveedorSolicitudProveedor $provider): array
    {
        $name = trim((string) ($provider->supplier_name ?? ''));
        $code = trim((string) $provider->code_supplier) ?: ($name !== '' ? $name : 'Sin código');
        $bullets = ['• *Código de proveedor:* '.$code];
        if ($name !== '') {
            $bullets[] = '• *Proveedor:* '.$name;
        }
        $bullets[] = '• *Producto:* '.$provider->product;

        return $bullets;
    }

    public function usdNoticeText(PagoProveedorSolicitudProveedor $provider, float $amount): string
    {
        $provider->loadMissing('solicitud');
        $request = $provider->solicitud;

        return implode("\n", [
            'Hola, '.($request?->client_name ?: 'cliente').' 👋',
            '',
            '¡Te confirmamos que el pago a tu proveedor se realizó exitosamente! ✅',
            '',
            ...$this->supplierBullets($provider),
            '• *N.° de consolidado:* '.($request?->consolidation ?: '—'),
            '• *Monto pagado:* $'.$this->amount($amount),
            '',
            '📎 Te compartimos la constancia de pago. Por favor, confirma la recepción con tu proveedor.',
            '',
            '¡Gracias por confiar en Pro Business! 🤝',
        ]);
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

    public function rmbNoticeText(PagoProveedorSolicitudProveedor $provider): string
    {
        $provider->loadMissing('solicitud');
        $request = $provider->solicitud;

        return implode("\n", [
            'Hola, '.($request?->client_name ?: 'cliente').' 👋',
            '',
            '¡Te confirmamos que el pago a tu proveedor se realizó exitosamente! ✅',
            '',
            ...$this->supplierBullets($provider),
            '• *N.° de consolidado:* '.($request?->consolidation ?: '—'),
            '',
            'Por favor, confirma la recepción con tu proveedor.',
            '',
            '¡Gracias por confiar en Pro Business! 🤝',
        ]);
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

    public function balanceStored(PagoProveedorSolicitudProveedor $provider): bool
    {
        return $this->alreadyStored($provider, 'balance');
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function storeBalance(PagoProveedorSolicitudProveedor $provider, array $actor): void
    {
        if ($this->alreadyStored($provider, 'balance')) {
            return;
        }
        $text = $this->balanceText($provider);
        if ($text === null) {
            return;
        }
        $this->store($provider, 'balance', $text, $actor);
    }

    public function balanceText(PagoProveedorSolicitudProveedor $provider): ?string
    {
        $provider->loadMissing(['solicitud', 'ejecuciones']);
        $collect = 0.0;
        $refund = 0.0;
        $perPayment = [];
        foreach ($provider->ejecuciones as $execution) {
            if (!in_array($execution->exchange_result, [PagoProveedorEjecucion::RESULT_COLLECT, PagoProveedorEjecucion::RESULT_REFUND], true)) {
                continue;
            }
            $difference = abs((float) $execution->exchange_difference_usd);
            if ($execution->exchange_result === PagoProveedorEjecucion::RESULT_COLLECT) {
                $collect += $difference;
            } else {
                $refund += $difference;
            }
            $perPayment[] = '• *Pago '.$execution->supplier_payment.':* $'.$this->amount($difference);
        }
        if ($perPayment === []) {
            return null;
        }
        $label = $refund > $collect ? 'Diferencia a devolver' : 'Diferencia a pagar';

        return implode("\n", [
            'Hola, '.($provider->solicitud?->client_name ?: 'cliente').' 👋',
            '',
            'Te compartimos el balance correspondiente al pago de tu proveedor:',
            '',
            ...$this->supplierBullets($provider),
            ...(count($perPayment) > 1 ? $perPayment : []),
            '• *'.$label.':* $'.$this->amount(abs($collect - $refund)),
            '',
            'Adjuntamos el balance en PDF con el detalle para tu revisión.',
            '',
            '✅ Por favor, confírmanos tu conformidad para continuar con el proceso.',
        ]);
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
