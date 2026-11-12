<?php

namespace App\Services\Verificacion;

use App\Events\VerificacionContadoresActualizados;
use Illuminate\Support\Facades\Log;

/**
 * Emite un único evento con los conteos actuales (o un subconjunto).
 * Se llama después de validar / observar / crear viático, etc.
 */
class VerificacionContadoresBroadcaster
{
    /** @var array */
    private static $pendingKeys = [];

    /** @var bool */
    private static $flushScheduled = false;

    /**
     * @param  array  $keys  claves a incluir; vacío = todas
     */
    public static function emit(array $keys = [])
    {
        try {
            if (empty($keys)) {
                self::$pendingKeys = ['*'];
            } elseif (!in_array('*', self::$pendingKeys, true)) {
                foreach ($keys as $key) {
                    if (!in_array($key, self::$pendingKeys, true)) {
                        self::$pendingKeys[] = $key;
                    }
                }
            }

            if (self::$flushScheduled) {
                return;
            }
            self::$flushScheduled = true;

            $runner = function () {
                self::$flushScheduled = false;
                $keys = self::$pendingKeys;
                self::$pendingKeys = [];
                $service = app(VerificacionContadoresService::class);
                $counts = in_array('*', $keys, true) || empty($keys)
                    ? $service->all()
                    : $service->slice($keys);
                event(new VerificacionContadoresActualizados($counts));
            };

            if (function_exists('app') && method_exists(app(), 'terminating')) {
                app()->terminating($runner);
            } else {
                $runner();
            }
        } catch (\Throwable $e) {
            self::$flushScheduled = false;
            self::$pendingKeys = [];
            Log::warning('VerificacionContadoresBroadcaster: no se pudo emitir', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
