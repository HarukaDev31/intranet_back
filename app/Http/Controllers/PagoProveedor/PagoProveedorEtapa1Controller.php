<?php

namespace App\Http\Controllers\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Http\Controllers\Controller;
use App\Services\PagoProveedor\PagoProveedorAutorizacion;
use App\Services\PagoProveedor\PagoProveedorEtapa1Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PagoProveedorEtapa1Controller extends Controller
{
    public function __construct(
        private readonly PagoProveedorAutorizacion $autorizacion,
        private readonly PagoProveedorEtapa1Service $etapa1,
    ) {
    }

    public function guardarRmb(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate([
                'vendedor_nombre' => ['required', 'string', 'max:150'],
                'wechat' => ['required', 'string', 'max:30'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->etapa1->guardarRmb($solicitudId, $proveedorId, $datos['vendedor_nombre'], $datos['wechat'], $actor),
            ]);
        });
    }

    public function enviarFormulario(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->etapa1->enviarFormulario($solicitudId, $proveedorId, $actor),
            ]);
        });
    }

    public function llenarPorVentas(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->etapa1->llenarPorVentas($solicitudId, $proveedorId, $request->all(), $actor),
            ]);
        });
    }

    public function solicitarValidacion(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->etapa1->solicitarValidacion($solicitudId, $proveedorId, $actor),
            ]);
        });
    }

    public function anularProveedor(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->etapa1->anularProveedor($solicitudId, $proveedorId, $actor),
            ]);
        });
    }

    public function pedirDatos(Request $request, int $solicitudId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->etapa1->vistaPreviaPedirDatos($solicitudId, $actor),
            ]);
        });
    }

    public function cuentas(Request $request, int $solicitudId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->etapa1->vistaPreviaCuentas($solicitudId, $actor),
            ]);
        });
    }

    public function registrarAbono(Request $request, int $solicitudId): JsonResponse
    {
        return $this->guardarAbono($request, $solicitudId, null);
    }

    public function actualizarAbono(Request $request, int $solicitudId, int $abonoId): JsonResponse
    {
        return $this->guardarAbono($request, $solicitudId, $abonoId);
    }

    public function anularAbono(Request $request, int $solicitudId, int $abonoId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $abonoId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->etapa1->anularAbono($solicitudId, $abonoId, $actor),
            ]);
        });
    }

    public function decidirExcedente(Request $request, int $solicitudId, int $abonoId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $abonoId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate([
                'decision' => ['required', 'in:aplicado_cuota,reembolso_solicitado'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->etapa1->decidirExcedente($solicitudId, $abonoId, $datos['decision'], $actor),
            ]);
        });
    }

    public function verFormulario(string $token): JsonResponse
    {
        return $this->responder(function () use ($token) {
            return response()->json(['success' => true, 'data' => $this->etapa1->verFormularioPublico($token)]);
        });
    }

    public function guardarFormulario(Request $request, string $token): JsonResponse
    {
        return $this->responder(function () use ($request, $token) {
            return response()->json([
                'success' => true,
                'data' => $this->etapa1->guardarFormularioPublico($token, $request->all()),
            ]);
        });
    }

    private function guardarAbono(Request $request, int $solicitudId, ?int $abonoId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $abonoId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $reglas = [
                'importe' => ['required', 'numeric', 'gt:0'],
                'fecha_pago' => ['required', 'date'],
                'numero_operacion' => ['required', 'string', 'max:80'],
                'banco' => ['required', 'in:bcp,interbank'],
                'interbancaria' => ['nullable', 'boolean'],
                'aplicaciones' => ['required'],
                'voucher' => [$abonoId ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ];
            $datos = $request->validate($reglas);
            $resultado = $this->etapa1->registrarAbono($solicitudId, $datos, $request->file('voucher'), $actor, $abonoId);

            return response()->json(['success' => true, 'data' => $resultado], $abonoId ? 200 : 201);
        });
    }

    private function responder(callable $accion): JsonResponse
    {
        try {
            return $accion();
        } catch (PagoProveedorException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Pago a proveedor etapa 1: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json(['success' => false, 'message' => 'No se pudo completar la operación.'], 500);
        }
    }
}
