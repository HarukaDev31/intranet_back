<?php

namespace App\Support\ExcelConfirmacion;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;

final class ExcelConfirmacionClientResponse
{
    /** @var array<string, array{title: string, message: string}> */
    private const CLIENT_MESSAGES = [
        'COTIZACION_NOT_FOUND' => [
            'title' => 'Enlace no disponible',
            'message' => 'No encontramos tu formulario de confirmación. Verifica el enlace que te envió coordinación o solicita uno nuevo.',
        ],
        'LABELS_NO_DISPONIBLES' => [
            'title' => 'No pudimos cargar el formulario',
            'message' => 'Hubo un problema al preparar las características del formulario. Intenta recargar la página en unos minutos.',
        ],
        'DATOS_NO_DISPONIBLES' => [
            'title' => 'No pudimos cargar tu información',
            'message' => 'Ocurrió un inconveniente al obtener tus datos. Recarga la página o vuelve a abrir el enlace.',
        ],
        'VALIDACION_DATOS' => [
            'title' => 'Revisa los datos ingresados',
            'message' => 'Algunos campos no son válidos o están incompletos. Corrígelos e intenta guardar nuevamente.',
        ],
        'PROVEEDOR_INVALIDO' => [
            'title' => 'Proveedor no reconocido',
            'message' => 'Uno de los proveedores no coincide con tu cotización. Recarga la página para actualizar la información.',
        ],
        'ITEM_INVALIDO' => [
            'title' => 'Producto no reconocido',
            'message' => 'Uno de los productos no coincide con tu cotización. Recarga la página e intenta guardar otra vez.',
        ],
        'FORMULARIO_CERRADO' => [
            'title' => 'Formulario cerrado',
            'message' => 'Coordinación cerró este proveedor. Ya no puedes editar ni guardar cambios hasta que lo reabran.',
        ],
        'GUARDADO_OK' => [
            'title' => 'Cambios guardados',
            'message' => 'Tu confirmación se guardó correctamente.',
        ],
        'ERROR_INTERNO' => [
            'title' => 'Algo salió mal',
            'message' => 'No pudimos completar la operación. Intenta nuevamente en unos minutos o contacta a coordinación.',
        ],
        'ENLACE_INVALIDO' => [
            'title' => 'Enlace inválido',
            'message' => 'El enlace que abriste no es válido. Solicita a coordinación un nuevo acceso al formulario.',
        ],
    ];

    public static function success(array $data = [], string $messageCode = 'GUARDADO_OK', int $status = 200): JsonResponse
    {
        $copy = self::CLIENT_MESSAGES[$messageCode] ?? self::CLIENT_MESSAGES['GUARDADO_OK'];

        return response()->json([
            'success' => true,
            'code' => $messageCode,
            'title' => $copy['title'],
            'message' => $copy['message'],
            'data' => $data === [] ? null : $data,
        ], $status);
    }

    public static function data(array $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    public static function error(string $code, int $status = 400, ?string $overrideMessage = null): JsonResponse
    {
        $copy = self::CLIENT_MESSAGES[$code] ?? self::CLIENT_MESSAGES['ERROR_INTERNO'];

        return response()->json([
            'success' => false,
            'code' => $code,
            'title' => $copy['title'],
            'message' => $overrideMessage ?: $copy['message'],
        ], $status);
    }

    public static function validationFailed(MessageBag $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 'VALIDACION_DATOS',
            'title' => self::CLIENT_MESSAGES['VALIDACION_DATOS']['title'],
            'message' => self::CLIENT_MESSAGES['VALIDACION_DATOS']['message'],
            'errors' => $errors,
        ], 422);
    }
}
