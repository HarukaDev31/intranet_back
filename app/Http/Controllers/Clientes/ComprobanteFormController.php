<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\ComprobanteForm;
use App\Jobs\SendComprobanteFormNotificationJob;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;

class ComprobanteFormController extends Controller
{
    /**
     * Devuelve las cotizaciones confirmadas del contenedor para el select de importadores.
     * GET /clientes/comprobante-form/{idContenedor}
     */
    public function getClientes($idContenedor)
    {
        try {
            $contenedor = Contenedor::find($idContenedor);
            if (!$contenedor) {
                return response()->json(['success' => false, 'message' => 'Carga no encontrada'], 404);
            }

            $cotizaciones = Cotizacion::where('id_contenedor', $idContenedor)
                ->whereNull('id_cliente_importacion')
                ->where('estado_cotizador', 'CONFIRMADO')
                ->whereNotNull('estado_cliente')
                ->get()
                ->map(fn($c) => [
                    'value' => $c->uuid,
                    'label' => $c->nombre,
                ]);

            return response()->json([
                'success' => true,
                'data'    => $cotizaciones,
                'carga'   => $contenedor->carga,
            ]);
        } catch (\Exception $e) {
            Log::error('ComprobanteFormController@getClientes', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Guarda el formulario de comprobante enviado por el cliente.
     * POST /clientes/comprobante-form/{idContenedor}
     */
    public function store(Request $request, $idContenedor)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $validated = $request->validate([
                'importador'       => 'required|string',   // uuid de la cotización
                'tipo_comprobante' => 'required|in:BOLETA,FACTURA',
                'destino_entrega'  => 'nullable|string|max:500',
                'razon_social'     => 'required_if:tipo_comprobante,FACTURA|nullable|string|max:255',
                'ruc'              => 'required_if:tipo_comprobante,FACTURA|nullable|string|max:20',
                'nombre_completo'  => 'required_if:tipo_comprobante,BOLETA|nullable|string|max:255',
                'dni_carnet'       => 'required_if:tipo_comprobante,BOLETA|nullable|string|max:20',
            ]);

            $cotizacion = Cotizacion::where('uuid', $validated['importador'])->first();
            if (!$cotizacion) {
                return response()->json(['success' => false, 'message' => 'Importador no encontrado'], 404);
            }

            // Crear o actualizar el formulario para esta cotización
            $form = ComprobanteForm::updateOrCreate(
                ['id_cotizacion' => $cotizacion->id],
                [
                    'id_contenedor'    => (int) $idContenedor,
                    'id_user'          => $user->id,
                    'tipo_comprobante' => $validated['tipo_comprobante'],
                    'destino_entrega'  => $validated['destino_entrega'] ?? null,
                    'razon_social'     => $validated['razon_social'] ?? null,
                    'ruc'              => $validated['ruc'] ?? null,
                    'nombre_completo'  => $validated['nombre_completo'] ?? null,
                    'dni_carnet'       => $validated['dni_carnet'] ?? null,
                ]
            );

            // Enviar notificación WhatsApp a administración
            SendComprobanteFormNotificationJob::dispatch($form->id);

            return response()->json([
                'success' => true,
                'message' => 'Formulario guardado correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('ComprobanteFormController@store', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Devuelve el formulario de comprobante de una cotización (para vista interna).
     * GET /clientes/comprobante-form/cotizacion/{idCotizacion}
     */
    public function getFormByCotizacion($idCotizacion)
    {
        try {
            $form = ComprobanteForm::where('id_cotizacion', $idCotizacion)->first();

            if (!$form) {
                return response()->json([
                    'success' => false,
                    'message' => 'Formulario no encontrado',
                    'data'    => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $form,
            ]);
        } catch (\Exception $e) {
            Log::error('ComprobanteFormController@getFormByCotizacion', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualiza el formulario de comprobante (edicion interna por contabilidad).
     * PUT /carga-consolidada/contenedor/factura-guia/contabilidad/comprobante-form/{idCotizacion}
     */
    public function updateForm(Request $request, $idCotizacion)
    {
        try {
            $form = ComprobanteForm::where('id_cotizacion', $idCotizacion)->first();

            if (!$form) {
                return response()->json(['success' => false, 'message' => 'Formulario no encontrado'], 404);
            }

            $form->tipo_comprobante = $request->input('tipo_comprobante', $form->tipo_comprobante);
            $form->destino_entrega  = $request->input('destino_entrega', $form->destino_entrega);
            $form->razon_social     = $request->input('razon_social', $form->razon_social);
            $form->ruc              = $request->input('ruc', $form->ruc);
            $form->nombre_completo  = $request->input('nombre_completo', $form->nombre_completo);
            $form->dni_carnet       = $request->input('dni_carnet', $form->dni_carnet);
            $form->save();

            return response()->json(['success' => true, 'message' => 'Formulario actualizado correctamente', 'data' => $form]);
        } catch (\Exception $e) {
            Log::error('ComprobanteFormController@updateForm', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
