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
            Log::info('FcmPushService: sin usuarios destinatarios, no se envía nada.', ['title' => $title]);
            return;
        }

        $devices = UsuarioDevice::query()
            ->whereIn('id_usuario', $usuarioIds)
            ->pluck('fcm_token');

        if ($devices->isEmpty()) {
            Log::info('FcmPushService: los usuarios destinatarios no tienen dispositivos registrados en usuario_device.', [
                'usuario_ids' => $usuarioIds,
                'title' => $title,
            ]);
            return;
        }

        try {
            $messaging = $this->makeMessaging();
        } catch (\Throwable $e) {
            Log::warning('FcmPushService: no se pudo inicializar Firebase Messaging (revisar FIREBASE_CREDENTIALS/FIREBASE_PROJECT_ID en .env). ' . $e->getMessage());
            return;
        }

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        try {
            $report = $messaging->sendMulticast($message, $devices->all());
        } catch (\Throwable $e) {
            Log::warning('FcmPushService: fallo al enviar multicast a Firebase. ' . $e->getMessage());
            return;
        }

        $tokensInvalidos = [];
        $exitosos = 0;
        foreach ($report->getItems() as $item) {
            if (!$item->isFailure()) {
                $exitosos++;
                continue;
            }
            if ($item->messageTargetWasInvalid() || $item->messageWasSentToUnknownToken()) {
                $tokensInvalidos[] = $item->target()->value();
            } else {
                Log::warning('FcmPushService: falló el envío a un token. ' . optional($item->error())->getMessage());
            }
        }

        Log::info('FcmPushService: envío completado.', [
            'title' => $title,
            'dispositivos' => $devices->count(),
            'exitosos' => $exitosos,
            'tokens_invalidos_eliminados' => count($tokensInvalidos),
        ]);

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
        } else {
            Log::warning('FcmPushService: no se encontró el archivo de credenciales de Firebase en ' . $credentials . ' — se intentará sin credenciales explícitas (probablemente falle).');
        }

        return $factory->createMessaging();
    }
}
