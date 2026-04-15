<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\ComprobanteForm;
use App\Models\User;
use App\Models\UsuarioDatosFacturacion;
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
            $user = JWTAuth::parseToken()->authenticate();
            $userId = (int) $user->id;

            $contenedor = Contenedor::find($idContenedor);
            if (!$contenedor) {
                return response()->json(['success' => false, 'message' => 'Carga no encontrada'], 404);
            }

            $cotizacionIds = Cotizacion::where('id_contenedor', $idContenedor)
                ->whereNull('id_cliente_importacion')
                ->where('estado_cotizador', 'CONFIRMADO')
                ->whereNotNull('estado_cliente')
                ->pluck('id');

            $formsByCotizacion = ComprobanteForm::where('id_contenedor', (int) $idContenedor)
                ->whereIn('id_cotizacion', $cotizacionIds)
                ->get()
                ->keyBy('id_cotizacion');

            $eligibleIds = $cotizacionIds->filter(function ($cid) use ($formsByCotizacion, $userId) {
                if (!$formsByCotizacion->has($cid)) {
                    return true;
                }
                $form = $formsByCotizacion->get($cid);

                return (int) $form->id_user === $userId;
            });

            $cotizaciones = Cotizacion::whereIn('id', $eligibleIds)
                ->orderBy('nombre')
                ->get()
                ->map(fn ($c) => [
                    'value' => $c->uuid,
                    'label' => $c->nombre,
                ]);

            $misRegistros = ComprobanteForm::query()
                ->with(['cotizacion' => function ($q) {
                    $q->select('id', 'uuid', 'nombre');
                }])
                ->where('id_contenedor', (int) $idContenedor)
                ->whereIn('id_cotizacion', $cotizacionIds)
                ->where('id_user', $userId)
                ->get()
                ->map(function (ComprobanteForm $form) {
                    $cot = $form->cotizacion;
                    $uuid = $cot ? $cot->uuid : null;

                    return [
                        'id'                 => $form->id,
                        'importador_uuid'    => $uuid,
                        'importador_label'   => $cot ? $cot->nombre : null,
                        'tipo_comprobante'   => $form->tipo_comprobante,
                        'destino_entrega'    => $form->destino_entrega,
                        'razon_social'       => $form->razon_social,
                        'ruc'                => $form->ruc,
                        'domicilio_fiscal'   => $form->domicilio_fiscal,
                        'nombre_completo'    => $form->nombre_completo,
                        'dni_carnet'         => $form->dni_carnet,
                    ];
                })
                ->values();

            return response()->json([
                'success'       => true,
                'data'          => $cotizaciones,
                'carga'         => $contenedor->carga,
                'mis_registros' => $misRegistros,
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

            if ($request->input('tipo_comprobante') === 'FACTURA' && $request->has('ruc')) {
                $request->merge([
                    'ruc' => preg_replace('/\D/', '', (string) $request->input('ruc')),
                ]);
            }
            foreach (['razon_social', 'nombre_completo', 'domicilio_fiscal', 'dni_carnet'] as $field) {
                if ($request->has($field) && is_string($request->input($field))) {
                    $request->merge([$field => trim($request->input($field))]);
                }
            }

            $validated = $request->validate([
                'importador'       => 'required|string|min:1',
                'tipo_comprobante' => 'required|in:BOLETA,FACTURA',
                'destino_entrega'  => 'required|string|in:Lima,Provincia',
                'razon_social'     => [
                    'exclude_if:tipo_comprobante,BOLETA',
                    'required_if:tipo_comprobante,FACTURA',
                    'string',
                    'min:3',
                    'max:255',
                ],
                'ruc'              => [
                    'exclude_if:tipo_comprobante,BOLETA',
                    'required_if:tipo_comprobante,FACTURA',
                    'digits:11',
                ],
                'domicilio_fiscal' => [
                    'exclude_if:tipo_comprobante,BOLETA',
                    'required_if:tipo_comprobante,FACTURA',
                    'string',
                    'min:10',
                    'max:2000',
                ],
                'nombre_completo'  => [
                    'exclude_if:tipo_comprobante,FACTURA',
                    'required_if:tipo_comprobante,BOLETA',
                    'string',
                    'min:3',
                    'max:255',
                ],
                'dni_carnet'       => [
                    'exclude_if:tipo_comprobante,FACTURA',
                    'required_if:tipo_comprobante,BOLETA',
                    'string',
                    'max:20',
                    'regex:/^(?:\d{8}|[A-Za-z0-9\-]{9,20})$/',
                ],
            ], [
                'importador.required'         => 'Debe seleccionar un importador.',
                'tipo_comprobante.required'    => 'Debe seleccionar el tipo de comprobante.',
                'destino_entrega.required'     => 'Debe seleccionar el destino de entrega.',
                'destino_entrega.in'           => 'El destino de entrega no es válido.',
                'ruc.required_if'              => 'El RUC es obligatorio para factura.',
                'ruc.digits'                   => 'El RUC debe tener exactamente 11 dígitos.',
                'razon_social.required_if'     => 'La razón social es obligatoria para factura.',
                'razon_social.min'             => 'La razón social debe tener al menos 3 caracteres.',
                'domicilio_fiscal.required_if' => 'El domicilio fiscal es obligatorio para factura.',
                'domicilio_fiscal.min'         => 'El domicilio fiscal debe tener al menos 10 caracteres.',
                'nombre_completo.required_if'  => 'El nombre completo es obligatorio para boleta.',
                'nombre_completo.min'          => 'El nombre completo debe tener al menos 3 caracteres.',
                'dni_carnet.required_if'       => 'El DNI o carné de extranjería es obligatorio para boleta.',
                'dni_carnet.regex'             => 'DNI: 8 dígitos. Carné extranjería: entre 9 y 20 caracteres (letras, números o guión).',
            ]);

            $cotizacion = Cotizacion::where('uuid', $validated['importador'])->first();
            if (!$cotizacion) {
                return response()->json(['success' => false, 'message' => 'Importador no encontrado'], 404);
            }

            if ((int) $cotizacion->id_contenedor !== (int) $idContenedor) {
                return response()->json(['success' => false, 'message' => 'La cotización no pertenece a esta carga'], 422);
            }

            $existing = ComprobanteForm::where('id_cotizacion', $cotizacion->id)->first();
            if ($existing) {
                if ((int) $existing->id_user !== (int) $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Este importador ya fue registrado por otro usuario.',
                    ], 403);
                }
            }

            $base = [
                'id_contenedor'    => (int) $idContenedor,
                'id_user'          => $user->id,
                'tipo_comprobante' => $validated['tipo_comprobante'],
                'destino_entrega'  => $validated['destino_entrega'] ?? null,
            ];

            if ($validated['tipo_comprobante'] === 'FACTURA') {
                $base['razon_social']     = $validated['razon_social'] ?? null;
                $base['ruc']            = $validated['ruc'] ?? null;
                $base['domicilio_fiscal'] = $validated['domicilio_fiscal'] ?? null;
                $base['nombre_completo']  = null;
                $base['dni_carnet']       = null;
            } else {
                $base['nombre_completo']  = $validated['nombre_completo'] ?? null;
                $base['dni_carnet']       = $validated['dni_carnet'] ?? null;
                $base['razon_social']     = null;
                $base['ruc']              = null;
                $base['domicilio_fiscal'] = null;
            }

            // Crear o actualizar el formulario para esta cotización
            $form = ComprobanteForm::updateOrCreate(
                ['id_cotizacion' => $cotizacion->id],
                $base
            );

            // Historial de datos de facturación por usuario:
            // cada envío del formulario registra una nueva fila.
            UsuarioDatosFacturacion::create([
                'id_user' => (int) $user->id,
                'destino' => in_array(($base['destino_entrega'] ?? null), ['Lima', 'Provincia'])
                    ? $base['destino_entrega']
                    : null,
                'nombre_completo' => $base['nombre_completo'] ?? null,
                'dni' => $base['dni_carnet'] ?? null,
                'ruc' => $base['ruc'] ?? null,
                'razon_social' => $base['razon_social'] ?? null,
                'domicilio_fiscal' => $base['domicilio_fiscal'] ?? null,
            ]);

            if ($form->wasRecentlyCreated) {
                SendComprobanteFormNotificationJob::dispatch($form->id);
            }

            if ($validated['tipo_comprobante'] === 'FACTURA' && ! empty($base['domicilio_fiscal'])) {
                User::whereKey($user->id)->update([
                    'domicilio_fiscal' => $base['domicilio_fiscal'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $form->wasRecentlyCreated
                    ? 'Formulario guardado correctamente'
                    : 'Formulario actualizado correctamente',
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

            // Incluir datos del usuario que registró el formulario (para UI interna).
            $registeredBy = null;
            if (!empty($form->id_user)) {
                $u = User::find($form->id_user);
                if ($u) {
                    $registeredBy = [
                        'id' => (int) $u->id,
                        'name' => $u->name,
                        'lastname' => $u->lastname,
                        'email' => $u->email,
                        'photo_url' => $u->photo_url,
                    ];
                }
            }
            $form->registered_by = $registeredBy;

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
            $form->domicilio_fiscal = $request->input('domicilio_fiscal', $form->domicilio_fiscal);
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
