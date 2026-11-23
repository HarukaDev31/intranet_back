<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use App\Models\CargaConsolidada\Cotizacion;
use App\Services\Auth\ImpersonacionClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class VerComoClienteController extends Controller
{
    private const MENSAJES = [
        'SIN_CUENTA' => 'Este cliente todavía no tiene una cuenta en el portal.',
        'CUENTA_AMBIGUA' => 'Hay más de una cuenta del portal con los datos de este cliente.',
    ];

    /** Link de un solo uso para ver el portal como el cliente de la cotización (solo Coordinación). */
    public function store(Request $request, int $idCotizacion, ImpersonacionClienteService $impersonacion): JsonResponse
    {
        $cotizacion = Cotizacion::query()->find($idCotizacion);
        if (! $cotizacion instanceof Cotizacion) {
            return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
        }

        $cuenta = $impersonacion->cuentaDeCotizacion($cotizacion);
        if ($cuenta['user'] === null) {
            return response()->json([
                'success' => false,
                'code' => $cuenta['error'],
                'message' => self::MENSAJES[$cuenta['error']] ?? 'No se pudo abrir el portal del cliente.',
            ], $cuenta['error'] === 'SIN_CUENTA' ? 404 : 409);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $impersonacion->crearLink($cuenta['user'], (int) JWTAuth::user()->getKey(), $idCotizacion, $request->ip()),
            ],
        ]);
    }
}
