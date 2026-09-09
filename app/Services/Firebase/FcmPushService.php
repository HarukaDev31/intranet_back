<?php

namespace App\Services\Firebase;

use App\Models\UsuarioDevice;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmPushService
{
    /**
     * Envía una notificación push a todos los dispositivos registrados de los usuarios indicados.
     * Elimina de `usuario_device` los tokens que Firebase reporte como inválidos/no registrados.
     *
     * @param array<int, int> $usuarioIds
     * @param string $title
     * @param string $body
     * @param array<string, string> $data
     * @return void
     */
    public function sendToUsuarios(array $usuarioIds, string $title, string $body, array $data = [])
    {
        $usuarioIds = array_values(array_unique(array_filter($usuarioIds)));
        if (empty($usuarioIds)) {
            return;
        }

        $devices = UsuarioDevice::query()
            ->whereIn('id_usuario', $usuarioIds)
            ->pluck('fcm_token');

        if ($devices->isEmpty()) {
            return;
        }

        try {
            $messaging = $this->makeMessaging();
        } catch (\Throwable $e) {
            Log::warning('FcmPushService — no se pudo inicializar Firebase Messaging: ' . $e->getMessage());
            return;
        }

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        try {
            $report = $messaging->sendMulticast($message, $devices->all());
        } catch (\Throwable $e) {
            Log::warning('FcmPushService — fallo al enviar multicast: ' . $e->getMessage());
            return;
        }

        $tokensInvalidos = [];
        foreach ($report->getItems() as $item) {
            if (!$item->isFailure()) {
                continue;
            }
            if ($item->messageTargetWasInvalid() || $item->messageWasSentToUnknownToken()) {
                $tokensInvalidos[] = $item->target()->value();
            }
        }

        if (!empty($tokensInvalidos)) {
            UsuarioDevice::query()->whereIn('fcm_token', $tokensInvalidos)->delete();
        }
    }

    /**
     * @return \Kreait\Firebase\Contract\Messaging
     */
    protected function makeMessaging()
    {
        $factory = new Factory();

        $credentials = config('firebase.credentials');
        if ($credentials && file_exists($credentials)) {
            $factory = $factory->withServiceAccount($credentials);
        }

        return $factory->createMessaging();
    }
}
