<?php

namespace App\Http\Controllers\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Http\Controllers\Controller;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Services\PagoProveedor\PagoProveedorAutorizacion;
use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Services\PagoProveedor\PagoProveedorConfirmacionService;
use App\Services\PagoProveedor\PagoProveedorReembolsoService;
use App\Services\PagoProveedor\PagoProveedorYuanService;
use App\Services\PagoProveedor\PagoProveedorEtapa1Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PagoProveedorEtapa1Controller extends Controller
{
    use RespondeConResource;

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

            return $this->datos($this->etapa1->guardarRmb($solicitudId, $proveedorId, $datos['vendedor_nombre'], $datos['wechat'], $actor));
        });
    }

    public function enviarFormulario(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return $this->datos($this->etapa1->enviarFormulario($solicitudId, $proveedorId, $actor));
        });
    }

    public function llenarPorVentas(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return $this->datos($this->etapa1->llenarPorVentas($solicitudId, $proveedorId, $request->all(), $actor));
        });
    }

    public function solicitarValidacion(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return $this->datos($this->etapa1->solicitarValidacion($solicitudId, $proveedorId, $actor));
        });
    }

    public function anularProveedor(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate(['motivo' => ['required', 'string', 'max:500']]);

            return $this->datos($this->etapa1->anularProveedor($solicitudId, $proveedorId, trim($datos['motivo']), $actor));
        });
    }

    public function observarProveedor(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate(['motivo' => ['required', 'string', 'max:500']]);

            return $this->datos($this->etapa1->observarProveedor($solicitudId, $proveedorId, trim($datos['motivo']), $actor));
        });
    }

    public function levantarObservacion(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate(['motivo' => ['required', 'string', 'max:500']]);

            return $this->datos($this->etapa1->levantarObservacionVentas($solicitudId, $proveedorId, trim($datos['motivo']), $actor));
        });
    }

    public function candidatosPedirDatos(Request $request, int $solicitudId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId) {
            $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => $this->etapa1->candidatosPedirDatos($solicitudId),
            ]);
        });
    }

    public function pedirDatos(Request $request, int $solicitudId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate([
                'proveedores' => ['nullable', 'array'],
                'proveedores.*' => ['integer'],
                'vista_previa' => ['nullable', 'boolean'],
            ]);

            return $this->datos($this->etapa1->vistaPreviaPedirDatos(
                $solicitudId,
                $actor,
                $datos['proveedores'] ?? null,
                (bool) ($datos['vista_previa'] ?? false)
            ));
        });
    }

    public function enviarPedirDatos(Request $request, int $solicitudId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate([
                'proveedores' => ['nullable', 'array'],
                'proveedores.*' => ['integer'],
            ]);

            return $this->datos($this->etapa1->enviarPedirDatos(
                $solicitudId,
                $actor,
                $datos['proveedores'] ?? null
            ));
        });
    }

    public function cuentas(Request $request, int $solicitudId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return $this->datos($this->etapa1->vistaPreviaCuentas($solicitudId, $actor));
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

            return $this->datos($this->etapa1->anularAbono($solicitudId, $abonoId, $actor));
        });
    }

    public function decidirExcedente(Request $request, int $solicitudId, int $abonoId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $abonoId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate([
                'decision' => ['required', 'in:aplicado_cuota,reembolso_solicitado'],
            ]);

            return $this->datos($this->etapa1->decidirExcedente($solicitudId, $abonoId, $datos['decision'], $actor));
        });
    }

    public function decidirCuota(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate([
                'choice' => ['required', 'in:'.PagoProveedorSolicitudProveedor::CHOICE_PROCEED.','.PagoProveedorSolicitudProveedor::CHOICE_CANCEL],
            ]);

            return $this->datos(app(PagoProveedorConfirmacionService::class)->decideSecondInstallment(
                $solicitudId,
                $proveedorId,
                $datos['choice'],
                $actor
            ));
        });
    }

    public function confirmarTipoCambio(Request $request, int $solicitudId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate([
                'tipo_cambio' => ['required', 'numeric', 'gt:0'],
            ]);

            return $this->datos(app(PagoProveedorConfirmacionService::class)->confirmExchangeRate(
                $solicitudId,
                (float) $datos['tipo_cambio'],
                $actor
            ));
        });
    }

    public function decidirPago(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate([
                'choice' => ['required', 'in:'.PagoProveedorSolicitudProveedor::CHOICE_PROCEED.','.PagoProveedorSolicitudProveedor::PAYMENT_CANCEL.','.PagoProveedorSolicitudProveedor::PAYMENT_REFUND],
                'reason' => ['nullable', 'string', 'max:500'],
            ]);

            return $this->datos(app(PagoProveedorConfirmacionService::class)->decideSecondPayment(
                $solicitudId,
                $proveedorId,
                $datos['choice'],
                $datos['reason'] ?? null,
                $actor
            ));
        });
    }

    public function enviarBalance(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $resultado = app(PagoProveedorConfirmacionService::class)->sendBalance($solicitudId, $proveedorId, $actor);
            $payload = $this->datos($resultado['solicitud'])->getData(true);
            $payload['pdf'] = $resultado['pdf'];

            return response()->json($payload);
        });
    }

    public function vistaPreviaBalance(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => app(PagoProveedorConfirmacionService::class)->balancePreview($solicitudId, $proveedorId),
            ]);
        });
    }

    public function descargarBalance(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return response()->json([
                'success' => true,
                'data' => app(PagoProveedorConfirmacionService::class)->downloadBalance($solicitudId, $proveedorId, $actor),
            ]);
        });
    }

    public function registrarDiferencia(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate([
                'importe' => ['required', 'numeric', 'gt:0'],
                'fecha' => ['required', 'date'],
                'banco' => ['required', 'in:'.PagoProveedorAbono::BANK_BCP.','.PagoProveedorAbono::BANK_INTERBANK],
                'numero_operacion' => ['required', 'string', 'max:80'],
                'voucher' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ]);

            return $this->datos(app(PagoProveedorConfirmacionService::class)->registerExchangeVoucher(
                $solicitudId,
                $proveedorId,
                (float) $datos['importe'],
                $datos['fecha'],
                $datos['banco'],
                $datos['numero_operacion'],
                $datos['voucher'],
                $actor
            ));
        });
    }

    public function solicitarReembolso(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return $this->datos(app(PagoProveedorReembolsoService::class)->request($solicitudId, $proveedorId, $actor));
        });
    }

    public function completarProveedor(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());

            return $this->datos(app(PagoProveedorConfirmacionService::class)->complete(
                $solicitudId,
                $proveedorId,
                $actor
            ));
        });
    }

    public function elegirContinuacion(Request $request, int $solicitudId, int $abonoId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $abonoId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate([
                'proveedores' => ['required', 'array', 'min:1'],
                'proveedores.*' => ['integer'],
            ]);

            return $this->datos($this->etapa1->elegirContinuacion($solicitudId, $abonoId, $datos['proveedores'], $actor));
        });
    }

    public function levantarChina(Request $request, int $solicitudId, int $proveedorId): JsonResponse
    {
        return $this->responder(function () use ($request, $solicitudId, $proveedorId) {
            $actor = $this->autorizacion->exigirVentas($request->user());
            $datos = $request->validate([
                'vendedor' => ['required', 'string', 'max:120'],
                'wechat' => ['required', 'string', 'max:40'],
            ]);

            return $this->datos(app(PagoProveedorYuanService::class)->lift(
                $solicitudId,
                $proveedorId,
                $datos['vendedor'],
                $datos['wechat'],
                $actor
            ));
        });
    }

    public function verFormulario(string $token): JsonResponse
    {
        return $this->responder(function () use ($token) {
            return $this->datos($this->etapa1->verFormularioPublico($token));
        });
    }

    public function guardarFormulario(Request $request, string $token): JsonResponse
    {
        return $this->responder(function () use ($request, $token) {
            return $this->datos($this->etapa1->guardarFormularioPublico($token, $request->all()));
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

            return $this->datos($resultado, $abonoId ? 200 : 201);
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
