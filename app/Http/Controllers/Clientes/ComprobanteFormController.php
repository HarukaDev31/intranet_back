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
use App\Helpers\UserLookupHelper;
use App\Helpers\ComprobanteFormResolverHelper;
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

            $misRegistrosDb = ComprobanteForm::query()
                ->with([
                    'cotizacion' => function ($q) {
                        $q->select('id', 'uuid', 'nombre');
                    },
                    'distrito' => function ($q) {
                        $q->select('ID_Distrito', 'No_Distrito');
                    },
                ])
                ->where('id_contenedor', (int) $idContenedor)
                ->whereIn('id_cotizacion', $cotizacionIds)
                ->where('id_user', $userId)
                ->get()
                ->map(function (ComprobanteForm $form) {
                    $cot = $form->cotizacion;
                    $uuid = $cot ? $cot->uuid : null;
                    $dist = $form->distrito;

                    return [
                        'id'                 => $form->id,
                        'importador_uuid'    => $uuid,
                        'importador_label'   => $cot ? $cot->nombre : null,
                        'tipo_comprobante'   => $form->tipo_comprobante,
                        'destino_entrega'    => $form->destino_entrega,
                        'razon_social'       => $form->razon_social,
                        'ruc'                => $form->ruc,
                        'domicilio_fiscal'   => $form->domicilio_fiscal,
                        'distrito_id'        => $form->distrito_id,
                        'distrito_nombre'    => $dist ? $dist->No_Distrito : null,
                        'nombre_completo'    => $form->nombre_completo,
                        'dni_carnet'         => $form->dni_carnet,
                        'prefill_historial'  => false,
                    ];
                })
                ->values();

            /** Si la cotización aún no tiene fila en consolidado_comprobante_forms, sugerir datos del último registro en usuario_datos_facturacion del mismo usuario. */
            $misRegistrosPrefill = collect();
            $latestUdf = UsuarioDatosFacturacion::query()
                ->where('id_user', $userId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($latestUdf) {
                $tipoHistorial = $this->inferirTipoComprobanteDesdeUsuarioDatosFacturacion($latestUdf);
                $cotizacionesSinForm = $tipoHistorial === null ? collect() : Cotizacion::query()
                    ->whereIn('id', $eligibleIds)
                    ->whereNotIn('id', $formsByCotizacion->keys())
                    ->orderBy('nombre')
                    ->get(['id', 'uuid', 'nombre']);

                foreach ($cotizacionesSinForm as $cot) {
                    $row = [
                        'id'                 => null,
                        'importador_uuid'    => $cot->uuid,
                        'importador_label'   => $cot->nombre,
                        'tipo_comprobante'   => $tipoHistorial,
                        'destino_entrega'    => in_array($latestUdf->destino, ['Lima', 'Provincia'], true)
                            ? $latestUdf->destino
                            : null,
                        'razon_social'       => null,
                        'ruc'                => null,
                        'domicilio_fiscal'   => null,
                        'distrito_id'        => null,
                        'distrito_nombre'    => null,
                        'nombre_completo'    => null,
                        'dni_carnet'         => null,
                        'prefill_historial'  => true,
                    ];

                    if ($tipoHistorial === 'FACTURA') {
                        $row['razon_social'] = $latestUdf->razon_social;
                        $row['ruc'] = $latestUdf->ruc !== null && $latestUdf->ruc !== ''
                            ? preg_replace('/\D/', '', (string) $latestUdf->ruc)
                            : null;
                        $row['domicilio_fiscal'] = $latestUdf->domicilio_fiscal;
                    } elseif ($tipoHistorial === 'BOLETA') {
                        $row['nombre_completo'] = $latestUdf->nombre_completo;
                        $row['dni_carnet'] = $latestUdf->dni;
                    }

                    $misRegistrosPrefill->push($row);
                }
            }

            $misRegistros = $misRegistrosDb->concat($misRegistrosPrefill)->values();

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
                'distrito_id'      => [
                    'exclude_if:tipo_comprobante,BOLETA',
                    'required_if:tipo_comprobante,FACTURA',
                    'integer',
                    'exists:distrito,ID_Distrito',
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
                'distrito_id.required_if'      => 'El distrito es obligatorio para factura.',
                'distrito_id.exists'          => 'El distrito seleccionado no es válido.',
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
                $base['distrito_id']      = isset($validated['distrito_id']) ? (int) $validated['distrito_id'] : null;
                $base['nombre_completo']  = null;
                $base['dni_carnet']       = null;
            } else {
                $base['nombre_completo']  = $validated['nombre_completo'] ?? null;
                $base['dni_carnet']       = $validated['dni_carnet'] ?? null;
                $base['razon_social']     = null;
                $base['ruc']              = null;
                $base['domicilio_fiscal'] = null;
                $base['distrito_id']      = null;
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
     * GET /carga-consolidada/contenedor/factura-guia/contabilidad/comprobante-form/{idCotizacion}
     *
     * Prioridad de la fuente de datos:
     *   1) Última fila en usuario_datos_facturacion vinculada al cliente (historial más reciente).
     *   2) Fila en consolidado_comprobante_forms (si no hay historial).
     *
     * Cuando existen ambas, se usa el historial como fuente de los campos pero se preserva
     * id / created_at / updated_at / registered_by del ComprobanteForm para que la UI siga
     * pudiendo abrir el formulario asociado.
     */
    public function getFormByCotizacion($idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada',
                    'data'    => null,
                ], 404);
            }

            $form = ComprobanteForm::where('id_cotizacion', $idCotizacion)->first();
            $udf = $this->findUsuarioDatosFacturacionForCotizacion($cotizacion);

            // Prioridad 1: último registro en usuario_datos_facturacion.
            if ($udf) {
                $synthetic = $this->buildSyntheticComprobanteFormFromUsuarioDatosFacturacion($udf, $cotizacion);
                if ($synthetic) {
                    if ($form) {
                        $synthetic['id'] = (int) $form->id;
                        $synthetic['created_at'] = $form->created_at ? $form->created_at->toIso8601String() : $synthetic['created_at'];
                        $synthetic['updated_at'] = $form->updated_at ? $form->updated_at->toIso8601String() : null;
                        $synthetic['id_user'] = $form->id_user !== null ? (int) $form->id_user : $synthetic['id_user'];
                        $synthetic['distrito_id'] = $form->distrito_id !== null ? (int) $form->distrito_id : $synthetic['distrito_id'];
                        $synthetic['registered_by'] = $this->buildRegisteredByPayload($form->id_user);
                        $synthetic['prefill_from_usuario_datos_facturacion'] = false;
                    }

                    return response()->json([
                        'success' => true,
                        'data'    => $synthetic,
                        'message' => $form
                            ? 'Datos sincronizados desde el último registro del historial de facturación.'
                            : 'Datos mostrados desde el historial de facturación del usuario (sin formulario consolidado).',
                    ]);
                }
            }

            // Prioridad 2: ComprobanteForm directo (no hay historial usable).
            if ($form) {
                $form->registered_by = $this->buildRegisteredByPayload($form->id_user);

                return response()->json([
                    'success' => true,
                    'data'    => $form,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Formulario no encontrado',
                'data'    => null,
            ], 404);
        } catch (\Exception $e) {
            Log::error('ComprobanteFormController@getFormByCotizacion', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Arma el payload "registered_by" usado por la UI a partir de un id_user.
     */
    private function buildRegisteredByPayload($idUser): ?array
    {
        if (empty($idUser)) {
            return null;
        }

        $u = User::find($idUser);
        if (!$u) {
            return null;
        }

        return [
            'id'        => (int) $u->id,
            'name'      => $u->name,
            'lastname'  => $u->lastname,
            'email'     => $u->email,
            'photo_url' => $u->photo_url,
        ];
    }

    /**
     * Delegado a ComprobanteFormResolverHelper para mantener una sola fuente de verdad.
     *
     * @return UsuarioDatosFacturacion|null
     */
    private function findUsuarioDatosFacturacionForCotizacion(Cotizacion $cotizacion)
    {
        return ComprobanteFormResolverHelper::findLatestUsuarioDatosFacturacion($cotizacion);
    }

    /**
     * Delegado a ComprobanteFormResolverHelper. Añade 'registered_by' = null por compatibilidad
     * con la respuesta histórica de este endpoint.
     */
    private function buildSyntheticComprobanteFormFromUsuarioDatosFacturacion(UsuarioDatosFacturacion $udf, Cotizacion $cotizacion)
    {
        $base = ComprobanteFormResolverHelper::buildSyntheticFromUdf($udf, $cotizacion);
        if ($base === null) {
            return null;
        }
        $base['registered_by'] = null;
        return $base;
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
                $cotizacion = Cotizacion::find($idCotizacion);
                if (!$cotizacion) {
                    return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
                }

                $udf = $this->findUsuarioDatosFacturacionForCotizacion($cotizacion);
                $form = new ComprobanteForm();
                $form->id_cotizacion = (int) $idCotizacion;
                $form->id_contenedor = $cotizacion->id_contenedor !== null ? (int) $cotizacion->id_contenedor : null;
                $form->id_user = $udf && $udf->id_user ? (int) $udf->id_user : null;
            }

            $form->tipo_comprobante = $request->input('tipo_comprobante', $form->tipo_comprobante);
            $form->destino_entrega  = $request->input('destino_entrega', $form->destino_entrega);
            $form->razon_social     = $request->input('razon_social', $form->razon_social);
            $form->ruc              = $request->input('ruc', $form->ruc);
            $form->domicilio_fiscal = $request->input('domicilio_fiscal', $form->domicilio_fiscal);
            if ($request->has('distrito_id')) {
                $form->distrito_id = $request->input('distrito_id') !== null && $request->input('distrito_id') !== ''
                    ? (int) $request->input('distrito_id')
                    : null;
            }
            $form->nombre_completo  = $request->input('nombre_completo', $form->nombre_completo);
            $form->dni_carnet       = $request->input('dni_carnet', $form->dni_carnet);
            $form->save();
            $this->appendUsuarioDatosFacturacion($form);

            return response()->json(['success' => true, 'message' => 'Formulario actualizado correctamente', 'data' => $form]);
        } catch (\Exception $e) {
            Log::error('ComprobanteFormController@updateForm', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Cada edición del comprobante agrega una nueva fila al historial usuario_datos_facturacion.
     * Si el ComprobanteForm no tiene id_user, intenta resolverlo desde la cotización con UserLookupHelper.
     */
    private function appendUsuarioDatosFacturacion(ComprobanteForm $form): void
    {
        $idUser = $this->resolveIdUserParaHistorial($form);
        if ($idUser <= 0) {
            Log::info('appendUsuarioDatosFacturacion: no se pudo resolver id_user para historial de facturacion', [
                'form_id' => $form->id,
                'id_cotizacion' => $form->id_cotizacion,
            ]);
            return;
        }

        $destino = in_array($form->destino_entrega, ['Lima', 'Provincia'], true)
            ? $form->destino_entrega
            : null;

        $payload = [
            'id_user' => $idUser,
            'destino' => $destino,
            'nombre_completo' => null,
            'dni' => null,
            'ruc' => null,
            'razon_social' => null,
            'domicilio_fiscal' => null,
        ];

        if ($form->tipo_comprobante === 'FACTURA') {
            $payload['ruc'] = $form->ruc !== null && $form->ruc !== ''
                ? preg_replace('/\D/', '', (string) $form->ruc)
                : null;
            $payload['razon_social'] = $form->razon_social;
            $payload['domicilio_fiscal'] = $form->domicilio_fiscal;
        } else {
            $payload['nombre_completo'] = $form->nombre_completo;
            $payload['dni'] = $form->dni_carnet;
        }

        UsuarioDatosFacturacion::create($payload);
    }

    /**
     * Devuelve el id_user del cliente para registrar el historial. Si el form ya lo trae, lo usa.
     * Si no, intenta resolverlo desde los datos de contacto de la cotización via UserLookupHelper.
     * Persiste el id_user resuelto en el ComprobanteForm para que ediciones futuras lo reutilicen.
     *
     * @return int 0 si no se pudo resolver.
     */
    private function resolveIdUserParaHistorial(ComprobanteForm $form): int
    {
        $idUser = (int) ($form->id_user ?? 0);
        if ($idUser > 0) {
            return $idUser;
        }

        if (empty($form->id_cotizacion)) {
            return 0;
        }

        $cotizacion = Cotizacion::find($form->id_cotizacion);
        if (!$cotizacion) {
            return 0;
        }

        try {
            $user = UserLookupHelper::findUserByContact(
                $cotizacion->correo ?? null,
                $cotizacion->telefono ?? null,
                $cotizacion->documento ?? null
            );
        } catch (\Exception $e) {
            Log::warning('resolveIdUserParaHistorial: error en UserLookupHelper', [
                'form_id' => $form->id,
                'id_cotizacion' => $form->id_cotizacion,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }

        if (!$user || empty($user->id)) {
            return 0;
        }

        $idUser = (int) $user->id;

        $form->id_user = $idUser;
        $form->save();

        return $idUser;
    }

    /**
     * Delegado a ComprobanteFormResolverHelper.
     */
    private function inferirTipoComprobanteDesdeUsuarioDatosFacturacion(UsuarioDatosFacturacion $udf): ?string
    {
        return ComprobanteFormResolverHelper::inferirTipoComprobante($udf);
    }
}
