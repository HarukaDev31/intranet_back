<?php

namespace App\Services\Delivery;

use App\Mail\DeliveryFormLinkCoordinationMail;
use App\Traits\MailTrait;
use Illuminate\Support\Facades\Log;

/**
 * Envía a COORDINATION_EMAIL una copia del mensaje de link de formulario de entrega (Lima/Provincia).
 */
class DeliveryFormLinkCoordinationNotifier
{
    use MailTrait;

    public function notify(
        string $messagePrincipal,
        ?string $messageSecundario,
        string $nombreCliente,
        $carga,
        int $idCotizacion,
        ?string $telefonoCliente,
        ?int $typeForm
    ): void {
        $to = trim((string) env('COORDINATION_EMAIL', ''));
        if ($to === '') {
            Log::warning('DeliveryFormLinkCoordinationNotifier: COORDINATION_EMAIL no configurado; se omite correo a coordinación');

            return;
        }

        $destino = null;
        if ($typeForm === 1) {
            $destino = 'Lima';
        } elseif ($typeForm === 0) {
            $destino = 'Provincia';
        }

        $sections = [
            ['title' => 'Mensaje principal (WhatsApp)', 'body' => $messagePrincipal],
        ];

        if ($messageSecundario !== null && trim($messageSecundario) !== '') {
            $sections[] = ['title' => 'Mensaje secundario (WhatsApp)', 'body' => $messageSecundario];
        }

        try {
            $this->sendMailTo(
                $to,
                new DeliveryFormLinkCoordinationMail(
                    $nombreCliente,
                    $carga,
                    $idCotizacion,
                    $telefonoCliente,
                    $destino,
                    $sections
                )
            );

            Log::info('DeliveryFormLinkCoordinationNotifier: correo enviado a coordinación', [
                'to' => $to,
                'id_cotizacion' => $idCotizacion,
                'destino' => $destino,
            ]);
        } catch (\Throwable $e) {
            Log::error('DeliveryFormLinkCoordinationNotifier: error enviando correo a coordinación: ' . $e->getMessage(), [
                'id_cotizacion' => $idCotizacion,
            ]);
        }
    }
}
