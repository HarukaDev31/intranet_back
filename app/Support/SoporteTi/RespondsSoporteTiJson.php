<?php

namespace App\Support\SoporteTi;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Respuestas JSON uniformes para controladores Soporte TI.
 */
trait RespondsSoporteTiJson
{
    protected function soporteTiOk($data = null, $message = null, $status = 200): JsonResponse
    {
        $payload = array('success' => true);
        if ($message !== null) {
            $payload['message'] = $message;
        }
        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    protected function soporteTiFail(\Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            throw $e;
        }
        if ($e instanceof AuthorizationException) {
            return response()->json(array(
                'success' => false,
                'message' => $e->getMessage() ?: 'No autorizado',
            ), 403);
        }
        if ($e instanceof ModelNotFoundException) {
            return response()->json(array(
                'success' => false,
                'message' => 'No encontrado',
            ), 404);
        }
        if ($e instanceof \InvalidArgumentException) {
            return response()->json(array(
                'success' => false,
                'message' => $e->getMessage(),
            ), 422);
        }

        return response()->json(array(
            'success' => false,
            'message' => 'Error interno al procesar la solicitud.',
        ), 500);
    }
}
