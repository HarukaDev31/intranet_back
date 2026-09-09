<?php

namespace App\Http\Controllers;

use App\Models\UsuarioDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeviceTokenController extends Controller
{
    /**
     * Registra o actualiza el token FCM del dispositivo del usuario autenticado.
     * Se usa fuera del login (p. ej. cuando Firebase rota el token en onNewToken).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|max:512',
            'platform' => 'required|in:android,ios',
            'device_id' => 'nullable|string|max:191',
            'app_version' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $data = $validator->validated();

        UsuarioDevice::updateOrCreate(
            ['fcm_token' => $data['fcm_token']],
            [
                'id_usuario' => auth()->id(),
                'platform' => $data['platform'],
                'device_id' => $data['device_id'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'last_used_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Elimina el registro de un dispositivo (token FCM) del usuario autenticado.
     * Se llama típicamente en logout / desinstalación detectada.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $fcmToken = $request->input('fcm_token');
        $deviceId = $request->input('device_id');

        if (empty($fcmToken) && empty($deviceId)) {
            return response()->json([
                'success' => false,
                'message' => 'Debe indicar fcm_token o device_id',
            ], 422);
        }

        $query = UsuarioDevice::query()->where('id_usuario', auth()->id());
        if ($fcmToken) {
            $query->where('fcm_token', $fcmToken);
        } else {
            $query->where('device_id', $deviceId);
        }
        $query->delete();

        return response()->json(['success' => true]);
    }
}
