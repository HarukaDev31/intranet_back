<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\FacturaComercial;
use App\Models\CargaConsolidada\Comprobante;
use App\Models\CargaConsolidada\Detraccion;
use App\Services\CargaConsolidada\GeminiService;
use App\Traits\WhatsappTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Traits\FileTrait;
use App\Models\CargaConsolidada\ComprobanteForm;
use App\Models\CargaConsolidada\ConsolidadoDeliveryFormLima;
use App\Models\CargaConsolidada\ConsolidadoDeliveryFormProvince;
use App\Models\CargaConsolidada\GuiaRemision;
use App\Models\UsuarioDatosFacturacion;

class FacturaGuiaController extends Controller
{
    use WhatsappTrait;
    use FileTrait;

    /**
     * Genera una URL absoluta y firmada temporalmente para que el front solo la abra.
     */
    private function absoluteSignedFileUrl(string $routeName, array $params, \DateTimeInterface $expiration): string
    {
        $url = URL::temporarySignedRoute($routeName, $expiration, $params);
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = rtrim(config('app.url'), '/') . '/' . ltrim($url, '/');
        }
        return $url;
    }

    /**
     * Busca datos de facturación previos para decidir el mensaje de WhatsApp.
     * 1) Prioriza formulario actual por cotización.
     * 2) Si no existe, intenta por historial usando documento (dni/ruc) del cliente.
     */
    private function getDatosFacturacionParaMensaje(Cotizacion $cotizacion)
    {
        $formActual = ComprobanteForm::where('id_cotizacion', $cotizacion->id)->first();
        if ($formActual) {
            return [
                'es_antiguo' => true,
                'destino' => $formActual->destino_entrega ?: null,
                'nombre_completo' => $formActual->nombre_completo ?: null,
                'dni' => $formActual->dni_carnet ?: null,
                'ruc' => $formActual->ruc ?: null,
                'razon_social' => $formActual->razon_social ?: null,
                'domicilio_fiscal' => $formActual->domicilio_fiscal ?: null,
            ];
        }

        $documento = trim((string) ($cotizacion->documento ?? ''));
        if ($documento === '') {
            return null;
        }

        $historico = UsuarioDatosFacturacion::where('ruc', $documento)
            ->orWhere('dni', $documento)
            ->orderBy('id', 'desc')
            ->first();

        if (!$historico) {
            return null;
        }

        return [
            'es_antiguo' => true,
            'destino' => $historico->destino ?: null,
            'nombre_completo' => $historico->nombre_completo ?: null,
            'dni' => $historico->dni ?: null,
            'ruc' => $historico->ruc ?: null,
            'razon_social' => $historico->razon_social ?: null,
            'domicilio_fiscal' => $historico->domicilio_fiscal ?: null,
        ];
    }

    private function buildMensajeFormularioNuevo(Cotizacion $cotizacion, $idContenedor, $clientesUrlBase)
    {
        $carga = $cotizacion->contenedor ? $cotizacion->contenedor->carga : 'N/A';
        $link = rtrim($clientesUrlBase, '/') . '/formulario-comprobante/' . $idContenedor;

        return "Hola {$cotizacion->nombre} 🙋🏻‍♀️,\n\n" .
            "Somos del área contable de Pro Business.\n" .
            "Tu carga del consolidado #{$carga} ya está rumbo a Perú 🚢.\n\n" .
            "✅ Por favor completa el formulario para enviarte tu comprobante cuando recibas tus productos:\n" .
            "{$link}\n\n" .
            "Crearse una cuenta si en caso es su primera vez.";
    }

    private function buildMensajeFormularioAntiguo(Cotizacion $cotizacion, array $datosFacturacion)
    {
        $carga = $cotizacion->contenedor ? $cotizacion->contenedor->carga : 'N/A';
        $tipoComprobante = !empty($datosFacturacion['ruc'])
            ? 'FACTURA'
            : (!empty($datosFacturacion['dni']) ? 'BOLETA' : '-');

        $ruc = $datosFacturacion['ruc'] ?? '-';
        $razonSocial = $datosFacturacion['razon_social'] ?? '-';
        $domicilio = $datosFacturacion['domicilio_fiscal'] ?? '-';
        $destino = $datosFacturacion['destino'] ?? '-';

        return "Hola {$cotizacion->nombre} 🙋🏻‍♀️,\n\n" .
            "Somos del área contable de Pro Business.\n" .
            "Tu carga del consolidado #{$carga} ya está rumbo a Perú 🚢.\n\n" .
            "✅ Para enviarte tu comprobante al momento de la entrega, confirma tus datos:\n\n" .
            "Datos de facturación:\n" .
            "- Tipo de comprobante: {$tipoComprobante}\n" .
            "- RUC: {$ruc}\n" .
            "- Razón social: {$razonSocial}\n" .
            "- Domicilio fiscal: {$domicilio}\n\n" .
            "Datos logísticos:\n" .
            "- Entrega: {$destino}\n\n" .
            "Quedamos atentos a tu confirmación. 🙌🏼";
    }

    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedores/{idContenedor}/factura-guia",
     *     tags={"Factura y Guía"},
     *     summary="Obtener cotizaciones para factura y guía",
     *     description="Obtiene las cotizaciones confirmadas de un contenedor para gestión de facturas y guías",
     *     operationId="getContenedorFacturaGuia",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idContenedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Response(response=200, description="Cotizaciones obtenidas exitosamente")
     * )
     */
    public function getContenedorFacturaGuia(Request $request, $idContenedor)
    {
        $perPage = (int) $request->input('per_page', 10);
        $search = trim((string) $request->input('search', ''));
        $filters = [];
        if ($request->has('filters')) {
            $raw = $request->input('filters');
            $filters = is_string($raw) ? json_decode($raw, true) : $raw;
            $filters = is_array($filters) ? $filters : [];
        }

        $query = Cotizacion::select(
            'contenedor_consolidado_cotizacion.id',
            'contenedor_consolidado_cotizacion.nombre',
            'contenedor_consolidado_cotizacion.documento',
            'contenedor_consolidado_cotizacion.correo',
            'contenedor_consolidado_cotizacion.telefono',
            'contenedor_consolidado_cotizacion.id_tipo_cliente',
            'contenedor_consolidado_cotizacion.id_contenedor_pago',
            'contenedor_consolidado_cotizacion.estado_cotizacion_final',
            'contenedor_consolidado_cotizacion.cotizacion_final_url',
            'contenedor_consolidado_cotizacion.guia_remision_url',
            'contenedor_consolidado_cotizacion.monto',
            'contenedor_consolidado_cotizacion.registrado_comprobante_form',
            'contenedor_consolidado_tipo_cliente.name as tipo_cliente_nombre',
        )
            ->withSum('pagos as total_pagos_monto', 'monto')
            ->with(['facturasComerciales' => function ($q) {
                $q->select('id', 'quotation_id', 'file_name', 'file_path');
            }])
            ->with(['comprobantes' => function ($q) {
                $q->select('id', 'quotation_id', 'tipo_comprobante', 'valor_comprobante', 'tiene_detraccion', 'monto_detraccion_soles', 'file_name', 'file_path');
                $q->with(['constancia' => function ($q2) {
                    $q2->select('id', 'comprobante_id', 'monto_detraccion', 'file_path');
                }]);
            }])
            ->join(
                'contenedor_consolidado_tipo_cliente',
                'contenedor_consolidado_cotizacion.id_tipo_cliente',
                '=',
                'contenedor_consolidado_tipo_cliente.id'
            )
            ->orderBy('contenedor_consolidado_cotizacion.id', 'asc')
            ->where('contenedor_consolidado_cotizacion.id_contenedor', $idContenedor)
            ->whereNotNull('contenedor_consolidado_cotizacion.estado_cliente')
            ->whereNull('contenedor_consolidado_cotizacion.id_cliente_importacion')
            ->where('contenedor_consolidado_cotizacion.estado_cotizador', "CONFIRMADO");

        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('contenedor_consolidado_cotizacion.nombre', 'like', $term)
                    ->orWhere('contenedor_consolidado_cotizacion.telefono', 'like', $term);
            });
        }

        $registrado = $filters['registrado'] ?? null;
        if ($registrado === '1') {
            $query->where('contenedor_consolidado_cotizacion.registrado_comprobante_form', 1);
        } elseif ($registrado === '0') {
            $query->where(function ($q) {
                $q->whereNull('contenedor_consolidado_cotizacion.registrado_comprobante_form')
                    ->orWhere('contenedor_consolidado_cotizacion.registrado_comprobante_form', 0);
            });
        }

        $estadoCotizacion = $filters['estado_cotizacion_final'] ?? null;
        if (!empty($estadoCotizacion) && $estadoCotizacion !== 'todos') {
            $query->where('contenedor_consolidado_cotizacion.estado_cotizacion_final', $estadoCotizacion);
        }

        $query = $query->paginate($perPage);

        $items = collect($query->items())->map(function ($item) {
            $facturasComerciales = collect($item->facturasComerciales ?? []);
            $facturasComercialesMapped = $facturasComerciales->map(function ($f) {
                $signedUrl = !empty($f->file_path)
                    ? $this->absoluteSignedFileUrl('carga-consolidada.factura-comercial.file', ['id' => $f->id], now()->addMinutes(30))
                    : null;
                return [
                    'id' => $f->id,
                    'file_name' => $f->file_name ?? null,
                    'file_path' => $signedUrl,
                ];
            })->values()->all();

            $comprobantesRaw = $item->comprobantes ?? collect();
            $totalDetracciones = 0;
            $mappedComprobantes = $comprobantesRaw->map(function ($c) use (&$totalDetracciones) {
                $montoDetraccion = null;
                $detraccion_file_url = null;
                if ($c->tiene_detraccion) {
                    $montoDetraccion = (float) ($c->monto_detraccion_soles ?? ($c->constancia ? $c->constancia->monto_detraccion : null) ?? 0);
                    $totalDetracciones += $montoDetraccion;
                    if ($c->constancia && !empty($c->constancia->file_path)) {
                        $detraccion_file_url = $this->absoluteSignedFileUrl('carga-consolidada.contabilidad.constancia.file', ['id' => $c->constancia->id], now()->addMinutes(30));
                    }
                }
                $comprobanteSignedUrl = !empty($c->file_path)
                    ? $this->absoluteSignedFileUrl('carga-consolidada.contabilidad.comprobante.file', ['id' => $c->id], now()->addMinutes(30))
                    : null;
                return [
                    'id' => $c->id,
                    'tipo_comprobante' => $c->tipo_comprobante,
                    'valor_comprobante' => $c->valor_comprobante !== null ? round((float) $c->valor_comprobante, 2) : null,
                    'tiene_detraccion' => (bool) $c->tiene_detraccion,
                    'detraccion' => $montoDetraccion !== null ? [
                        'monto' => round($montoDetraccion, 2),
                        'monto_pagado' => ($c->constancia && $c->constancia->monto_detraccion !== null) ? round((float) $c->constancia->monto_detraccion, 2) : null,
                        'file_url' => $detraccion_file_url,
                    ] : null,
                    'comprobante_file_url' => $comprobanteSignedUrl,
                    'file_path' => $comprobanteSignedUrl,
                    'file_name' => $c->file_name ?? null,
                ];
            })->values()->all();

            $firstFacturaUrl = $facturasComercialesMapped[0]['file_path'] ?? null;
            // Guías de remisión (múltiples). Si no hay registros, fallback legacy del campo guia_remision_url.
            $guiasDb = GuiaRemision::where('quotation_id', $item->id)->orderBy('id', 'desc')->get();
            $guiasRemision = $guiasDb->map(function ($g) {
                return [
                    'id' => $g->id,
                    'file_name' => $g->file_name ?? 'Guía',
                    'file_url' => !empty($g->file_path)
                        ? $this->absoluteSignedFileUrl('carga-consolidada.guia-remision.file', ['id' => $g->id], now()->addMinutes(30))
                        : null,
                ];
            })->values()->all();

            $guiaUrlLegacy = !empty($item->guia_remision_url)
                ? $this->generateImageUrl('cargaconsolidada/guiaremision/' . $item->id . '/' . $item->guia_remision_url)
                : null;
            if (empty($guiasRemision) && $guiaUrlLegacy) {
                $guiasRemision = [['id' => 0, 'file_name' => 'Guía', 'file_url' => $guiaUrlLegacy]];
            }

            $comprobanteForm = ComprobanteForm::where('id_cotizacion', $item->id)->first();
            $registrado = (bool) ($item->registrado_comprobante_form ?? false);
            $tipoEntrega = $comprobanteForm ? $comprobanteForm->destino_entrega : null;
            $formTipoComprobante = $comprobanteForm ? $comprobanteForm->tipo_comprobante : null;

            return [
                'id_cotizacion' => $item->id,
                'nombre' => $item->nombre,
                'documento' => $item->documento,
                'correo' => $item->correo,
                'telefono' => $item->telefono,
                'tipo_cliente_nombre' => $item->tipo_cliente_nombre,
                'name' => $item->tipo_cliente_nombre,
                'id_contenedor_pago' => $item->id_contenedor_pago,
                'estado_cotizacion_final' => $item->estado_cotizacion_final,
                'cotizacion_final_url' => $item->cotizacion_final_url,
                'factura_comercial' => $firstFacturaUrl,
                'facturas_comerciales' => $facturasComercialesMapped,
                'guia_remision_url' => $guiaUrlLegacy,
                'guias_remision' => $guiasRemision,
                'registrado' => $registrado,
                'comprobante_form' => $comprobanteForm,
                'tipo_entrega' => $tipoEntrega,
                'form_tipo_comprobante' => $formTipoComprobante,
                'comprobantes' => $mappedComprobantes,
                'total_pagado' => (float) ($item->total_pagos_monto ?? 0),
                'total_pagado_confirmado' => (float) ($item->total_pagos_monto ?? 0),
                'monto_a_pagar' => (float) ($item->monto ?? 0),
            ];
        });

        return response()->json([
            'data' => $items,
            'pagination' => [
                'total' => $query->total(),
                'per_page' => $query->perPage(),
                'current_page' => $query->currentPage(),
                'last_page' => $query->lastPage(),
                'from' => $query->firstItem(),
                'to' => $query->lastItem()
            ],
            'success' => true
        ]);
    }
    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor/factura-guia/general/upload-guia-remision",
     *     tags={"Factura y Guía"},
     *     summary="Subir guía de remisión",
     *     description="Sube un archivo de guía de remisión para una cotización",
     *     operationId="uploadGuiaRemision",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="idCotizacion", type="integer"),
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Guía subida exitosamente")
     * )
     */
    public function uploadGuiaRemision(Request $request)
    {
        try {
            $idCotizacion = $request->idCotizacion;
            $file = $request->file('file');
            if (!$idCotizacion || !$file || !$file->isValid()) {
                return response()->json(['success' => false, 'message' => 'idCotizacion y file son requeridos'], 400);
            }
            $originalName = $file->getClientOriginalName();
            $fileSize     = $file->getSize();
            $mimeType     = $file->getMimeType();

            $storedName = time() . '_' . uniqid() . '_' . $originalName;
            $storedPath = $file->storeAs('cargaconsolidada/guiaremision/' . $idCotizacion, $storedName);

            // legacy: mantener último archivo en la cotización (compatibilidad)
            $cotizacion = Cotizacion::find($idCotizacion);
            if ($cotizacion) {
                $cotizacion->guia_remision_url = $storedName;
                $cotizacion->save();
            }

            // nuevo: guardar item en tabla de guías
            $guia = GuiaRemision::create([
                'quotation_id' => $idCotizacion,
                'file_name'    => $originalName,
                'file_path'    => $storedPath,
                'size'         => $fileSize,
                'mime_type'    => $mimeType,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Guia remision actualizada correctamente',
                'data' => [
                    'id'        => $guia->id,
                    'file_name' => $guia->file_name,
                    'file_url'  => $this->generateImageUrl($guia->file_path),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar guia remision: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Subir múltiples guías de remisión (batch) para una cotización.
     *
     * POST /carga-consolidada/contenedor/factura-guia/general/upload-guias-remision-batch
     * Body (multipart): idCotizacion, files[]
     */
    public function uploadGuiasRemisionBatch(Request $request)
    {
        try {
            $idCotizacion = $request->idCotizacion;
            $files = $request->file('files');

            if (!$idCotizacion || empty($files) || !is_array($files)) {
                return response()->json(['success' => false, 'message' => 'idCotizacion y files[] son requeridos'], 400);
            }

            $created = [];
            $lastStoredName = null;
            foreach ($files as $file) {
                if (!$file || !$file->isValid()) continue;
                $originalName = $file->getClientOriginalName();
                $fileSize     = $file->getSize();
                $mimeType     = $file->getMimeType();

                $storedName = time() . '_' . uniqid() . '_' . $originalName;
                $storedPath = $file->storeAs('cargaconsolidada/guiaremision/' . $idCotizacion, $storedName);
                $lastStoredName = $storedName;

                $guia = GuiaRemision::create([
                    'quotation_id' => $idCotizacion,
                    'file_name'    => $originalName,
                    'file_path'    => $storedPath,
                    'size'         => $fileSize,
                    'mime_type'    => $mimeType,
                ]);
                $created[] = [
                    'id'        => $guia->id,
                    'file_name' => $guia->file_name,
                    'file_url'  => $this->generateImageUrl($guia->file_path),
                ];
            }

            // legacy: apuntar al último si existe
            if ($lastStoredName) {
                $cotizacion = Cotizacion::find($idCotizacion);
                if ($cotizacion) {
                    $cotizacion->guia_remision_url = $lastStoredName;
                    $cotizacion->save();
                }
            }

            return response()->json([
                'success' => count($created) > 0,
                'message' => 'Guias subidas correctamente',
                'data'    => $created,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al subir guías: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Elimina un item de guía de remisión por ID.
     *
     * DELETE /carga-consolidada/contenedor/factura-guia/general/delete-guia-remision-item/{guiaId}
     */
    public function deleteGuiaRemisionItem($guiaId)
    {
        try {
            $guia = GuiaRemision::find($guiaId);
            if (!$guia) {
                return response()->json(['success' => false, 'message' => 'Guía no encontrada'], 404);
            }
            if (!empty($guia->file_path)) {
                $path = storage_path('app/' . $guia->file_path);
                if (file_exists($path)) unlink($path);
            }
            $guia->delete();
            return response()->json(['success' => true, 'message' => 'Guía eliminada correctamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al eliminar guía: ' . $e->getMessage()], 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor/factura-guia/general/upload-factura-comercial",
     *     tags={"Factura y Guía"},
     *     summary="Subir factura(s) comercial(es)",
     *     description="Sube uno o múltiples archivos de factura comercial para una cotización",
     *     operationId="uploadFacturaComercialFG",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="idCotizacion", type="integer"),
     *                 @OA\Property(
     *                     property="files[]",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary"),
     *                     description="Array de archivos de factura comercial"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Factura(s) subida(s) exitosamente")
     * )
     */
    public function uploadFacturaComercial(Request $request)
    {
        try {
            $idCotizacion = $request->idCotizacion;
            
            if (!$idCotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'El ID de cotización es requerido'
                ], 400);
            }

            // Verificar que la cotización existe
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }

            // Obtener los archivos (puede ser uno o múltiples)
            $files = $request->file('files');
            
            if (!$files || (is_array($files) && count($files) === 0)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se proporcionaron archivos para subir'
                ], 400);
            }

            // Asegurar que siempre sea un array
            if (!is_array($files)) {
                $files = [$files];
            }

            $uploadedFiles = [];
            $errors = [];

            foreach ($files as $file) {
                try {
                    // Validar que el archivo sea válido
                    if (!$file || !$file->isValid()) {
                        $errors[] = 'Archivo inválido: ' . ($file ? $file->getClientOriginalName() : 'desconocido');
                        continue;
                    }

                    $originalName = $file->getClientOriginalName();
                    $fileSize = $file->getSize();
                    $mimeType = $file->getMimeType();
                    
                    // Guardar el archivo en el almacenamiento
                    $storedPath = $file->storeAs(
                        'cargaconsolidada/facturacomercial/' . $idCotizacion,
                        $originalName
                    );

                    // Guardar el registro en la base de datos (tabla contenedor_consolidado_facturas_e)
                    $facturaComercial = FacturaComercial::create([
                        'quotation_id' => $idCotizacion,
                        'file_name' => $originalName,
                        'file_path' => $storedPath,
                        'size' => $fileSize,
                        'mime_type' => $mimeType,
                    ]);

                    $uploadedFiles[] = [
                        'id' => $facturaComercial->id,
                        'file_name' => $originalName,
                        'file_path' => $storedPath,
                        'size' => $fileSize,
                        'mime_type' => $mimeType,
                    ];

                    // Mantener compatibilidad: actualizar el campo factura_comercial en la cotización
                    // con el último archivo subido (para no romper funcionalidad existente)
                    $cotizacion->save();

                } catch (\Exception $e) {
                    $errors[] = 'Error al subir ' . ($file ? $file->getClientOriginalName() : 'archivo') . ': ' . $e->getMessage();
                    Log::error('Error al subir factura comercial individual', [
                        'quotation_id' => $idCotizacion,
                        'file' => $file ? $file->getClientOriginalName() : 'desconocido',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if (count($uploadedFiles) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo subir ningún archivo',
                    'errors' => $errors
                ], 500);
            }

            $message = count($uploadedFiles) === 1 
                ? 'Factura comercial subida correctamente'
                : count($uploadedFiles) . ' facturas comerciales subidas correctamente';

            $response = [
                'success' => true,
                'message' => $message,
                'files' => $uploadedFiles,
                'count' => count($uploadedFiles)
            ];

            if (count($errors) > 0) {
                $response['warnings'] = $errors;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Error al subir facturas comerciales', [
                'quotation_id' => $request->idCotizacion,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al subir facturas comerciales: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedor/factura-guia/general/{idContenedor}/headers",
     *     tags={"Factura y Guía"},
     *     summary="Obtener headers de factura y guía",
     *     description="Obtiene los headers de datos para factura y guía",
     *     operationId="getHeadersDataFG",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idContenedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Headers obtenidos exitosamente")
     * )
     */
    public function getHeadersData($idContenedor)
    {
        try {
            $contenedor = Contenedor::where('id', $idContenedor)->first();
            // Cotizaciones del contenedor (comprobantes/detracciones están por quotation_id = id de cotización)
            $cotizacionIds = Cotizacion::where('id_contenedor', $idContenedor)->pluck('id');
            $totalComprobantes = Comprobante::whereIn('quotation_id', $cotizacionIds)->sum('valor_comprobante');
            $totalDetracciones = Comprobante::whereIn('quotation_id', $cotizacionIds)->where('tiene_detraccion', true)->sum('monto_detraccion_soles');
            $detraccionPagado = Detraccion::whereIn('quotation_id', $cotizacionIds)->sum('monto_detraccion');
            $headers = [
                'total_comprobantes' => [
                    'value' => $totalComprobantes,
                    'label' => 'Total comprobantes',
                    'icon' => 'fas fa-file-alt'
                ],
                'total_detracciones' => [
                    'value' => $totalDetracciones,
                    'label' => 'Total detracciones',
                    'icon' => 'fas fa-money-bill-alt'
                ],
                'detraccion_pagado' => [
                    'value' => $detraccionPagado,
                    'label' => 'Detraccion pagado',
                    'icon' => 'fas fa-check-circle'
                ]
            ];
            return response()->json([
                'success' => true,
                'data' => array_values($headers),
                'carga' => $contenedor->carga ?? ''
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener headers: ' . $e->getMessage()
            ]);
        }
    }
    /**
     * @OA\Delete(
     *     path="/carga-consolidada/contenedor/factura-guia/general/delete-factura-comercial/{idFactura}",
     *     tags={"Factura y Guía"},
     *     summary="Eliminar factura comercial",
     *     description="Elimina una factura comercial específica por su ID",
     *     operationId="deleteFacturaComercialFG",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idFactura", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Factura eliminada exitosamente"),
     *     @OA\Response(response=404, description="Factura no encontrada")
     * )
     */
    public function deleteFacturaComercial($idFactura)
    {
        try {
            $facturaComercial = FacturaComercial::find($idFactura);
            
            if (!$facturaComercial) {
                //find factura comercial by id_cotizacion in table contenedor_consolidado_cotizacion
                $facturaComercial = Cotizacion::find($idFactura)->factura_comercial;
                if (!$facturaComercial) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Factura comercial no encontrada'
                    ], 404);
                }else{
                    unlink($this->generateImageUrl($facturaComercial));
                    Cotizacion::find($idFactura)->factura_comercial = null;
                    Cotizacion::find($idFactura)->save();
                    return response()->json([
                        'success' => true,
                        'message' => 'Factura comercial eliminada correctamente'
                    ]);
                }
            }

            // Eliminar el archivo físico
            $filePath = storage_path('app/' . $facturaComercial->file_path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Eliminar el registro de la base de datos
            $facturaComercial->delete();

            // Verificar si quedan más facturas para esta cotización
            $cotizacion = Cotizacion::find($facturaComercial->quotation_id);
            $facturasRestantes = FacturaComercial::where('quotation_id', $facturaComercial->id_cotizacion)->count();
            
            // Si no quedan facturas, limpiar el campo legacy en la cotización
            if ($cotizacion && $facturasRestantes === 0) {
                $cotizacion->factura_comercial = null;
                $cotizacion->save();
            } elseif ($cotizacion && $facturasRestantes > 0) {
                // Si quedan facturas, actualizar con la última factura (más reciente)
                $ultimaFactura = FacturaComercial::where('quotation_id', $facturaComercial->id_cotizacion)
                    ->orderBy('created_at', 'desc')
                    ->first();
                if ($ultimaFactura) {
                    $cotizacion->factura_comercial = $ultimaFactura->nombre_archivo;
                    $cotizacion->save();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Factura comercial eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar factura comercial', [
                'id_factura' => $idFactura,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar factura comercial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedor/factura-guia/general/get-facturas-comerciales/{idCotizacion}",
     *     tags={"Factura y Guía"},
     *     summary="Obtener facturas comerciales",
     *     description="Obtiene todas las facturas comerciales de una cotización",
     *     operationId="getFacturasComerciales",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idCotizacion", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Facturas obtenidas exitosamente")
     * )
     */
    public function getFacturasComerciales($idCotizacion)
    {
        try {
            $facturas = FacturaComercial::where('quotation_id', $idCotizacion)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $facturas,
                'count' => $facturas->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener facturas comerciales', [
                'id_cotizacion' => $idCotizacion,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener facturas comerciales: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Delete(
     *     path="/carga-consolidada/contenedor/factura-guia/general/delete-guia-remision/{idContenedor}",
     *     tags={"Factura y Guía"},
     *     summary="Eliminar guía de remisión",
     *     description="Elimina la guía de remisión de una cotización",
     *     operationId="deleteGuiaRemision",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idContenedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Guía eliminada exitosamente"),
     *     @OA\Response(response=404, description="Guía no encontrada")
     * )
     */
    public function deleteGuiaRemision($idContenedor)
    {
        $cotizacion = Cotizacion::find($idContenedor);
        if (!$cotizacion) {
            return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
        }

        // Eliminar todas las guías (nueva tabla)
        $guias = GuiaRemision::where('quotation_id', $cotizacion->id)->get();
        foreach ($guias as $g) {
            if (!empty($g->file_path)) {
                $path = storage_path('app/' . $g->file_path);
                if (file_exists($path)) unlink($path);
            }
            $g->delete();
        }

        // legacy: limpiar campo
        $cotizacion->guia_remision_url = null;
        $cotizacion->save();

        return response()->json(['success' => true, 'message' => 'Guías eliminadas correctamente']);
    }

    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor/factura-guia/send-factura/{idCotizacion}",
     *     tags={"Factura y Guía"},
     *     summary="Enviar factura por WhatsApp",
     *     description="Envía la factura comercial al cliente por WhatsApp",
     *     operationId="sendFactura",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idCotizacion", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Factura enviada exitosamente"),
     *     @OA\Response(response=404, description="Cotización no encontrada")
     * )
     *
     * Enviar factura comercial por WhatsApp
     */
    public function sendFactura($idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            
            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cotización no encontrada'
                ], 404);
            }

            // Validar que la factura existe
            if (!$cotizacion->factura_comercial) {
                return response()->json([
                    'success' => false,
                    'error' => 'No hay factura comercial disponible para esta cotización'
                ], 400);
            }

            // Obtener la ruta del archivo
            $filePath = storage_path('app/cargaconsolidada/facturacomercial/' . $idCotizacion . '/' . $cotizacion->factura_comercial);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'El archivo de factura comercial no se encuentra en el servidor'
                ], 404);
            }

            // Obtener teléfono del cliente
            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono);
            if (empty($telefono)) {
                return response()->json([
                    'success' => false,
                    'error' => 'El cliente no tiene un número de teléfono válido'
                ], 400);
            }

            // Formatear número de WhatsApp
            if (strlen($telefono) < 9) {
                $telefono = '51' . $telefono;
            }
            $numeroWhatsapp = $telefono . '@c.us';

            // Crear mensaje
            $contenedor = Contenedor::find($cotizacion->id_contenedor);
            $carga = $contenedor ? $contenedor->carga : 'N/A';
            /**message: Buenos tardes #nombrecliente 🙋🏻‍♀, te adjunto la factura de tu consolidado ##

✅ Verificar que el monto de crédito fiscal sea el correcto.
✅ Recordar, solo recuperan como crédito fiscal el 18% (IGV + IPM) que esta contemplado en su cotización final.
✅ El plazo máximo para notificar una observación de su comprobante es de 24 h. Después de este periodo, no será posible realizar modificaciones de ningún tipo. */
            $message = "Buenas tardes " . $cotizacion->nombre . " 🙋🏻‍♀, te adjunto la factura de tu consolidado #" . $carga . ".\n\n"  .
            "✅ Verificar que el monto de crédito fiscal sea el correcto.\n\n" .
            "✅ Recordar, solo recuperan como crédito fiscal el 18% (IGV + IPM) que esta contemplado en su cotización final.\n\n" .
            "✅ El plazo máximo para notificar una observación de su comprobante es de 24 h. Después de este periodo, no será posible realizar modificaciones de ningún tipo.";

            // Detectar MIME type del archivo
            $mimeType = mime_content_type($filePath);
            if (!$mimeType) {
                // Fallback a application/pdf si no se puede detectar
                $mimeType = 'application/pdf';
            }

            $result = $this->sendMedia($filePath, $mimeType, $message, $numeroWhatsapp, 0, 'administracion', $cotizacion->factura_comercial);

            if ($result === false) {
                Log::error('Error al enviar factura por WhatsApp: sendMedia devolvió false', [
                    'id_cotizacion' => $idCotizacion,
                    'telefono' => $numeroWhatsapp,
                    'archivo' => $cotizacion->factura_comercial
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al enviar la factura por WhatsApp: No se pudo procesar el archivo'
                ], 500);
            }

            // Verificar si es un array con la estructura esperada
            if (!is_array($result) || !isset($result['status'])) {
                Log::error('Error al enviar factura por WhatsApp: Respuesta inválida de sendMedia', [
                    'id_cotizacion' => $idCotizacion,
                    'result' => $result
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al enviar la factura por WhatsApp: Respuesta inválida del servicio'
                ], 500);
            }

            if ($result['status']) {
                Log::info('Factura comercial enviada por WhatsApp', [
                    'id_cotizacion' => $idCotizacion,
                    'telefono' => $numeroWhatsapp,
                    'archivo' => $cotizacion->factura_comercial
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Factura comercial enviada correctamente por WhatsApp',
                    'data' => [
                        'messageId' => $result['response']['messageId'] ?? null,
                        'sentAt' => now()->toISOString()
                    ]
                ]);
            } else {
                $errorMessage = $result['response']['error'] ?? 'Error desconocido al enviar el mensaje';
                Log::error('Error al enviar factura por WhatsApp', [
                    'id_cotizacion' => $idCotizacion,
                    'error' => $errorMessage,
                    'response' => $result['response'] ?? null
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al enviar la factura por WhatsApp: ' . $errorMessage
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Excepción al enviar factura por WhatsApp: ' . $e->getMessage(), [
                'id_cotizacion' => $idCotizacion,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al enviar la factura por WhatsApp: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor/factura-guia/send-guia/{idCotizacion}",
     *     tags={"Factura y Guía"},
     *     summary="Enviar guía por WhatsApp",
     *     description="Envía la guía de remisión al cliente por WhatsApp",
     *     operationId="sendGuia",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idCotizacion", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Guía enviada exitosamente"),
     *     @OA\Response(response=404, description="Cotización no encontrada")
     * )
     *
     * Enviar guía de remisión por WhatsApp
     */
    public function sendGuia($idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            
            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cotización no encontrada'
                ], 404);
            }

            // Validar que la guía existe: preferir legacy field; si está vacío o no existe, usar la última guía registrada.
            $fileName = $cotizacion->guia_remision_url;
            $filePath = $fileName
                ? storage_path('app/cargaconsolidada/guiaremision/' . $idCotizacion . '/' . $fileName)
                : null;

            if (!$filePath || !is_file($filePath)) {
                $lastGuia = GuiaRemision::where('quotation_id', $idCotizacion)->orderBy('id', 'desc')->first();
                if (!$lastGuia || empty($lastGuia->file_path)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'No hay guía de remisión disponible para esta cotización'
                    ], 400);
                }
                $filePath = storage_path('app/' . $lastGuia->file_path);
                $fileName = $lastGuia->file_name ?? basename($lastGuia->file_path);
            }
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'El archivo de guía de remisión no se encuentra en el servidor'
                ], 404);
            }

            // Obtener teléfono del cliente
            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono);
            if (empty($telefono)) {
                return response()->json([
                    'success' => false,
                    'error' => 'El cliente no tiene un número de teléfono válido'
                ], 400);
            }

            // Formatear número de WhatsApp
            if (strlen($telefono) < 9) {
                $telefono = '51' . $telefono;
            }
            $numeroWhatsapp = $telefono . '@c.us';

            // Crear mensaje
            $contenedor = Contenedor::find($cotizacion->id_contenedor);
            $carga = $contenedor ? $contenedor->carga : 'N/A';
            /**Hola [Nombre] 😊

Te envío tu Guía de Remisión del consolidado ## para que puedas realizar el recojo de tu mercadería.

🏢 Dirección de recojo:
Calle Río Nazca 243 – San Luis
📍 Referencia: Al costado de la Agencia Antezana

➡ MAPS: https://maps.app.goo.gl/5raLmkX65nNHB2Fr9

Cualquier duda nos escribe.  ¡Gracias! */
            $message =  "Hola " . $cotizacion->nombre . " 😊,\n\n" .
                       "Te envío tu Guía de Remisión del consolidado #" . $carga . " para que puedas realizar el recojo de tu mercadería.\n\n" .
                       "🏢 Dirección de recojo:\nCalle Río Nazca 243 – San Luis\n📍 Referencia: Al costado de la Agencia Antezana\n\n" .
                       "➡ MAPS: https://maps.app.goo.gl/5raLmkX65nNHB2Fr9\n\n" .
                       "Cualquier duda nos escribe.  ¡Gracias!";

            // Detectar MIME type del archivo
            $mimeType = mime_content_type($filePath);
            if (!$mimeType) {
                // Fallback a application/pdf si no se puede detectar
                $mimeType = 'application/pdf';
            }

            // Enviar documento por WhatsApp
            // sendMedia($filePath, $mimeType = null, $message = null, $phoneNumberId = null, $sleep = 0, $fromNumber = 'consolidado', $fileName = null)
            $result = $this->sendMedia($filePath, $mimeType, $message, $numeroWhatsapp, 0, 'administracion', $fileName);

            // Verificar si sendMedia devolvió false (error)
            if ($result === false) {
                Log::error('Error al enviar guía por WhatsApp: sendMedia devolvió false', [
                    'id_cotizacion' => $idCotizacion,
                    'telefono' => $numeroWhatsapp,
                    'archivo' => $cotizacion->guia_remision_url
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al enviar la guía de remisión por WhatsApp: No se pudo procesar el archivo'
                ], 500);
            }

            // Verificar si es un array con la estructura esperada
            if (!is_array($result) || !isset($result['status'])) {
                Log::error('Error al enviar guía por WhatsApp: Respuesta inválida de sendMedia', [
                    'id_cotizacion' => $idCotizacion,
                    'result' => $result
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al enviar la guía de remisión por WhatsApp: Respuesta inválida del servicio'
                ], 500);
            }

            if ($result['status']) {
                Log::info('Guía de remisión enviada por WhatsApp', [
                    'id_cotizacion' => $idCotizacion,
                    'telefono' => $numeroWhatsapp,
                    'archivo' => $cotizacion->guia_remision_url
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Guía de remisión enviada correctamente por WhatsApp',
                    'data' => [
                        'messageId' => $result['response']['messageId'] ?? null,
                        'sentAt' => now()->toISOString()
                    ]
                ]);
            } else {
                $errorMessage = $result['response']['error'] ?? 'Error desconocido al enviar el mensaje';
                Log::error('Error al enviar guía por WhatsApp', [
                    'id_cotizacion' => $idCotizacion,
                    'error' => $errorMessage,
                    'response' => $result['response'] ?? null
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al enviar la guía de remisión por WhatsApp: ' . $errorMessage
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Excepción al enviar guía por WhatsApp: ' . $e->getMessage(), [
                'id_cotizacion' => $idCotizacion,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al enviar la guía de remisión por WhatsApp: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONTABILIDAD — Comprobantes, Detracciones, Detalle, Enviar formulario
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sube un comprobante (factura/boleta) para una cotización.
     * Extrae tipo_comprobante y valor_comprobante usando Gemini 2.0 Flash.
     *
     * POST /carga-consolidada/contenedor/factura-guia/contabilidad/upload-comprobante
     * Body (multipart): idCotizacion, file
     */
    public function uploadComprobante(Request $request)
    {
        try {
            $idCotizacion = $request->idCotizacion;
            $file = $request->file('file');

            if (!$idCotizacion || !$file || !$file->isValid()) {
                return response()->json(['success' => false, 'message' => 'idCotizacion y file son requeridos'], 400);
            }

            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
            }

            $originalName = $file->getClientOriginalName();
            $fileSize     = $file->getSize();
            $mimeType     = $file->getMimeType();

            $storedPath = $file->storeAs(
                'cargaconsolidada/comprobantes/' . $idCotizacion,
                $originalName
            );

            $tipoComprobante        = null;
            $valorComprobante       = null;
            $tieneDetraccion        = false;
            $montoDetraccionDolares = null;
            $montoDetraccionSoles   = null;
            $extractedByAi          = false;

            $geminiSupportedMimes = [
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];

            if (in_array($mimeType, $geminiSupportedMimes)) {
                $gemini       = new GeminiService();
                $filePath     = storage_path('app/' . $storedPath);
                $geminiResult = $gemini->extractFromComprobante($filePath, $mimeType);

                if ($geminiResult['success']) {
                    $tipoComprobante        = $geminiResult['tipo_comprobante'];
                    $valorComprobante       = $geminiResult['valor_comprobante'];
                    $tieneDetraccion        = !empty($geminiResult['tiene_detraccion']);
                    $montoDetraccionDolares = $geminiResult['monto_detraccion_dolares'];
                    $montoDetraccionSoles   = $geminiResult['monto_detraccion_soles'];
                    $extractedByAi          = true;
                } else {
                    Log::warning('GeminiService no pudo extraer datos del comprobante', [
                        'quotation_id' => $idCotizacion,
                        'error'        => $geminiResult['error'],
                    ]);
                }
            }

            $comprobante = Comprobante::create([
                'quotation_id'             => $idCotizacion,
                'tipo_comprobante'         => $tipoComprobante,
                'valor_comprobante'        => $valorComprobante,
                'tiene_detraccion'         => $tieneDetraccion ? 1 : 0,
                'monto_detraccion_dolares' => $montoDetraccionDolares,
                'monto_detraccion_soles'   => $montoDetraccionSoles,
                'file_name'                => $originalName,
                'file_path'                => $storedPath,
                'size'                     => $fileSize,
                'mime_type'                => $mimeType,
                'extracted_by_ai'          => $extractedByAi ? 1 : 0,
            ]);
            $comprobante->file_url = $this->absoluteSignedFileUrl('carga-consolidada.contabilidad.comprobante.file', ['id' => $comprobante->id], now()->addMinutes(30));

            return response()->json([
                'success'   => true,
                'message'   => 'Comprobante subido correctamente',
                'data'      => $comprobante,
                'extracted' => $extractedByAi,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al subir comprobante', [
                'quotation_id' => $request->idCotizacion,
                'error'        => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error al subir comprobante: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sube múltiples comprobantes en una sola petición (batch) para una cotización.
     * Extrae datos con Gemini cuando aplica.
     *
     * POST /carga-consolidada/contenedor/factura-guia/contabilidad/upload-comprobantes-batch
     * Body (multipart): idCotizacion, files[]
     */
    public function uploadComprobantesBatch(Request $request)
    {
        $idCotizacion = $request->idCotizacion;
        $files = $request->file('files');

        if (!$idCotizacion || empty($files) || !is_array($files)) {
            return response()->json(['success' => false, 'message' => 'idCotizacion y files[] son requeridos'], 400);
        }

        $cotizacion = Cotizacion::find($idCotizacion);
        if (!$cotizacion) {
            return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
        }

        $created = [];
        $errors = [];

        foreach ($files as $idx => $file) {
            try {
                if (!$file || !$file->isValid()) {
                    $errors[] = ['index' => $idx, 'file_name' => null, 'message' => 'Archivo inválido'];
                    continue;
                }

                $originalName = $file->getClientOriginalName();
                $fileSize     = $file->getSize();
                $mimeType     = $file->getMimeType();

                // Evitar colisiones de nombres en batch
                $storedName = time() . '_' . uniqid() . '_' . $originalName;
                $storedPath = $file->storeAs('cargaconsolidada/comprobantes/' . $idCotizacion, $storedName);

                $tipoComprobante        = null;
                $valorComprobante       = null;
                $tieneDetraccion        = false;
                $montoDetraccionDolares = null;
                $montoDetraccionSoles   = null;
                $extractedByAi          = false;

                $geminiSupportedMimes = [
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ];

                if (in_array($mimeType, $geminiSupportedMimes)) {
                    $gemini       = new GeminiService();
                    $filePath     = storage_path('app/' . $storedPath);
                    $geminiResult = $gemini->extractFromComprobante($filePath, $mimeType);

                    if ($geminiResult['success']) {
                        $tipoComprobante        = $geminiResult['tipo_comprobante'];
                        $valorComprobante       = $geminiResult['valor_comprobante'];
                        $tieneDetraccion        = !empty($geminiResult['tiene_detraccion']);
                        $montoDetraccionDolares = $geminiResult['monto_detraccion_dolares'];
                        $montoDetraccionSoles   = $geminiResult['monto_detraccion_soles'];
                        $extractedByAi          = true;
                    } else {
                        Log::warning('GeminiService no pudo extraer datos del comprobante (batch)', [
                            'quotation_id' => $idCotizacion,
                            'file_name'    => $originalName,
                            'error'        => $geminiResult['error'],
                        ]);
                    }
                }

                $comprobante = Comprobante::create([
                    'quotation_id'             => $idCotizacion,
                    'tipo_comprobante'         => $tipoComprobante,
                    'valor_comprobante'        => $valorComprobante,
                    'tiene_detraccion'         => $tieneDetraccion ? 1 : 0,
                    'monto_detraccion_dolares' => $montoDetraccionDolares,
                    'monto_detraccion_soles'   => $montoDetraccionSoles,
                    // Guardamos el nombre original para mostrar en el front
                    'file_name'                => $originalName,
                    'file_path'                => $storedPath,
                    'size'                     => $fileSize,
                    'mime_type'                => $mimeType,
                    'extracted_by_ai'          => $extractedByAi ? 1 : 0,
                ]);
                $comprobante->file_url = $this->absoluteSignedFileUrl('carga-consolidada.contabilidad.comprobante.file', ['id' => $comprobante->id], now()->addMinutes(30));

                $created[] = [
                    'data' => $comprobante,
                    'extracted' => $extractedByAi,
                ];
            } catch (\Exception $e) {
                $errors[] = ['index' => $idx, 'file_name' => isset($originalName) ? $originalName : null, 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => count($created) > 0,
            'message' => count($errors) ? 'Batch completado con errores' : 'Batch completado correctamente',
            'created' => $created,
            'errors'  => $errors,
        ]);
    }

    /**
     * Sube la constancia de pago de detracción vinculada a un comprobante específico.
     * Extrae el monto del depósito usando Gemini 2.0 Flash.
     *
     * POST /carga-consolidada/contenedor/factura-guia/contabilidad/upload-constancia/{comprobanteId}
     * Body (multipart): file
     */
    public function uploadConstancia(Request $request, $comprobanteId)
    {
        try {
            $file = $request->file('file');

            if (!$file || !$file->isValid()) {
                return response()->json(['success' => false, 'message' => 'file es requerido'], 400);
            }

            $comprobante = Comprobante::find($comprobanteId);
            if (!$comprobante) {
                return response()->json(['success' => false, 'message' => 'Comprobante no encontrado'], 404);
            }

            // Si ya existe una constancia previa para este comprobante, eliminarla
            $constanciaPrevia = Detraccion::where('comprobante_id', $comprobanteId)->first();
            if ($constanciaPrevia) {
                $prevPath = storage_path('app/' . $constanciaPrevia->file_path);
                if (file_exists($prevPath)) {
                    unlink($prevPath);
                }
                $constanciaPrevia->delete();
            }

            $originalName = $file->getClientOriginalName();
            $fileSize     = $file->getSize();
            $mimeType     = $file->getMimeType();

            $storedPath = $file->storeAs(
                'cargaconsolidada/constancias/' . $comprobante->quotation_id,
                $originalName
            );

            $montoConstanciaSoles = null;
            $extractedByAi        = false;

            $geminiSupportedMimes = [
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];

            if (in_array($mimeType, $geminiSupportedMimes)) {
                $gemini       = new GeminiService();
                $filePath     = storage_path('app/' . $storedPath);
                $geminiResult = $gemini->extractFromConstancia($filePath, $mimeType);

                if ($geminiResult['success']) {
                    $montoConstanciaSoles = $geminiResult['monto_constancia_soles'];
                    $extractedByAi        = true;
                } else {
                    Log::warning('GeminiService no pudo extraer datos de la constancia', [
                        'comprobante_id' => $comprobanteId,
                        'error'          => $geminiResult['error'],
                    ]);
                }
            }

            $constancia = Detraccion::create([
                'quotation_id'    => $comprobante->quotation_id,
                'comprobante_id'  => $comprobante->id,
                'monto_detraccion'=> $montoConstanciaSoles,
                'file_name'       => $originalName,
                'file_path'       => $storedPath,
                'size'            => $fileSize,
                'mime_type'       => $mimeType,
                'extracted_by_ai' => $extractedByAi ? 1 : 0,
            ]);
            $constancia->file_url = $this->absoluteSignedFileUrl('carga-consolidada.contabilidad.constancia.file', ['id' => $constancia->id], now()->addMinutes(30));

            return response()->json([
                'success'   => true,
                'message'   => 'Constancia de pago subida correctamente',
                'data'      => $constancia,
                'extracted' => $extractedByAi,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al subir constancia de detracción', [
                'comprobante_id' => $comprobanteId,
                'error'          => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error al subir constancia: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sube múltiples constancias de detracción en una sola petición (batch).
     *
     * POST /carga-consolidada/contenedor/factura-guia/contabilidad/upload-constancias-batch
     * Body (multipart): comprobante_ids[], files[]
     */
    public function uploadConstanciasBatch(Request $request)
    {
        $comprobanteIds = $request->input('comprobante_ids', []);
        $files = $request->file('files');

        if (empty($comprobanteIds) || empty($files) || !is_array($comprobanteIds) || !is_array($files)) {
            return response()->json(['success' => false, 'message' => 'comprobante_ids[] y files[] son requeridos'], 400);
        }
        if (count($comprobanteIds) !== count($files)) {
            return response()->json(['success' => false, 'message' => 'comprobante_ids[] y files[] deben tener el mismo tamaño'], 400);
        }

        $created = [];
        $errors = [];

        foreach ($comprobanteIds as $idx => $comprobanteId) {
            try {
                $file = $files[$idx] ?? null;
                if (!$file || !$file->isValid()) {
                    $errors[] = ['index' => $idx, 'comprobante_id' => $comprobanteId, 'message' => 'Archivo inválido'];
                    continue;
                }

                $comprobante = Comprobante::find($comprobanteId);
                if (!$comprobante) {
                    $errors[] = ['index' => $idx, 'comprobante_id' => $comprobanteId, 'message' => 'Comprobante no encontrado'];
                    continue;
                }

                // Eliminar constancia previa si existe
                $constanciaPrevia = Detraccion::where('comprobante_id', $comprobanteId)->first();
                if ($constanciaPrevia) {
                    $prevPath = storage_path('app/' . $constanciaPrevia->file_path);
                    if (file_exists($prevPath)) {
                        unlink($prevPath);
                    }
                    $constanciaPrevia->delete();
                }

                $originalName = $file->getClientOriginalName();
                $fileSize     = $file->getSize();
                $mimeType     = $file->getMimeType();

                $storedName = time() . '_' . uniqid() . '_' . $originalName;
                $storedPath = $file->storeAs('cargaconsolidada/constancias/' . $comprobante->quotation_id, $storedName);

                $montoConstanciaSoles = null;
                $extractedByAi        = false;

                $geminiSupportedMimes = [
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ];

                if (in_array($mimeType, $geminiSupportedMimes)) {
                    $gemini       = new GeminiService();
                    $filePath     = storage_path('app/' . $storedPath);
                    $geminiResult = $gemini->extractFromConstancia($filePath, $mimeType);

                    if ($geminiResult['success']) {
                        $montoConstanciaSoles = $geminiResult['monto_constancia_soles'];
                        $extractedByAi        = true;
                    } else {
                        Log::warning('GeminiService no pudo extraer datos de la constancia (batch)', [
                            'comprobante_id' => $comprobanteId,
                            'file_name'      => $originalName,
                            'error'          => $geminiResult['error'],
                        ]);
                    }
                }

                $constancia = Detraccion::create([
                    'quotation_id'     => $comprobante->quotation_id,
                    'comprobante_id'   => $comprobante->id,
                    'monto_detraccion' => $montoConstanciaSoles,
                    'file_name'        => $originalName,
                    'file_path'        => $storedPath,
                    'size'             => $fileSize,
                    'mime_type'        => $mimeType,
                    'extracted_by_ai'  => $extractedByAi ? 1 : 0,
                ]);
                $constancia->file_url = $this->absoluteSignedFileUrl('carga-consolidada.contabilidad.constancia.file', ['id' => $constancia->id], now()->addMinutes(30));

                $created[] = [
                    'comprobante_id' => (int) $comprobanteId,
                    'data' => $constancia,
                    'extracted' => $extractedByAi,
                ];
            } catch (\Exception $e) {
                $errors[] = ['index' => $idx, 'comprobante_id' => $comprobanteId, 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => count($created) > 0,
            'message' => count($errors) ? 'Batch completado con errores' : 'Batch completado correctamente',
            'created' => $created,
            'errors'  => $errors,
        ]);
    }

    /**
     * Elimina un comprobante por su ID.
     *
     * DELETE /carga-consolidada/contenedor/factura-guia/contabilidad/delete-comprobante/{id}
     */
    public function deleteComprobante($id)
    {
        try {
            $comprobante = Comprobante::find($id);
            if (!$comprobante) {
                return response()->json(['success' => false, 'message' => 'Comprobante no encontrado'], 404);
            }

            // Eliminar constancia de pago vinculada si existe
            $constancia = Detraccion::where('comprobante_id', $id)->first();
            if ($constancia) {
                $constanciaPath = storage_path('app/' . $constancia->file_path);
                if (file_exists($constanciaPath)) {
                    unlink($constanciaPath);
                }
                $constancia->delete();
            }

            $filePath = storage_path('app/' . $comprobante->file_path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $comprobante->delete();

            return response()->json(['success' => true, 'message' => 'Comprobante eliminado correctamente']);
        } catch (\Exception $e) {
            Log::error('Error al eliminar comprobante', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al eliminar comprobante: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Elimina una detracción por su ID.
     *
     * DELETE /carga-consolidada/contenedor/factura-guia/contabilidad/delete-detraccion/{id}
     */
    public function deleteDetraccion($id)
    {
        try {
            $detraccion = Detraccion::find($id);
            if (!$detraccion) {
                return response()->json(['success' => false, 'message' => 'Detracción no encontrada'], 404);
            }

            $filePath = storage_path('app/' . $detraccion->file_path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $detraccion->delete();

            return response()->json(['success' => true, 'message' => 'Detracción eliminada correctamente']);
        } catch (\Exception $e) {
            Log::error('Error al eliminar detracción', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al eliminar detracción: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sirve el archivo de un comprobante por ID (evita URLs con rutas concatenadas).
     * GET .../contabilidad/comprobante/{id}/file
     */
    public function serveComprobanteFile($id)
    {
        $comprobante = Comprobante::find($id);
        if (!$comprobante || empty($comprobante->file_path)) {
            abort(404, 'Comprobante no encontrado');
        }
        $fullPath = storage_path('app/' . $comprobante->file_path);
        if (!is_file($fullPath)) {
            Log::warning('FacturaGuia: Archivo de comprobante no encontrado en disco', ['id' => $id, 'file_path' => $comprobante->file_path]);
            abort(404, 'Archivo no encontrado');
        }
        $mime = $comprobante->mime_type ?: mime_content_type($fullPath);
        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($comprobante->file_path) . '"',
        ]);
    }

    /**
     * Sirve el archivo de una factura comercial por ID (URL firmada).
     * GET .../general/factura-comercial/{id}/file
     */
    public function serveFacturaComercialFile($id)
    {
        $factura = FacturaComercial::find($id);
        if (!$factura || empty($factura->file_path)) {
            abort(404, 'Factura comercial no encontrada');
        }
        $fullPath = storage_path('app/' . $factura->file_path);
        if (!is_file($fullPath)) {
            Log::warning('FacturaGuia: Archivo de factura comercial no encontrado en disco', ['id' => $id, 'file_path' => $factura->file_path]);
            abort(404, 'Archivo no encontrado');
        }
        $mime = $factura->mime_type ?: mime_content_type($fullPath);
        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($factura->file_path) . '"',
        ]);
    }

    /**
     * Sirve el archivo de una constancia de detracción por ID.
     * GET .../contabilidad/constancia/{id}/file
     */
    public function serveConstanciaFile($id)
    {
        $detraccion = Detraccion::find($id);
        if (!$detraccion || empty($detraccion->file_path)) {
            abort(404, 'Constancia no encontrada');
        }
        $fullPath = storage_path('app/' . $detraccion->file_path);
        if (!is_file($fullPath)) {
            Log::warning('Archivo de constancia no encontrado en disco', ['id' => $id, 'file_path' => $detraccion->file_path]);
            abort(404, 'Archivo no encontrado');
        }
        $mime = $detraccion->mime_type ?: mime_content_type($fullPath);
        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($detraccion->file_path) . '"',
        ]);
    }

    /**
     * Sirve el archivo de una guía de remisión por ID (tabla contenedor_consolidado_guias_remision).
     * GET .../general/guia-remision/{id}/file
     */
    public function serveGuiaRemisionFile($id)
    {
        $guia = GuiaRemision::find($id);
        if (!$guia || empty($guia->file_path)) {
            abort(404, 'Guía no encontrada');
        }
        $fullPath = storage_path('app/' . $guia->file_path);
        if (!is_file($fullPath)) {
            Log::warning('FacturaGuia: Archivo de guía no encontrado en disco', ['id' => $id, 'file_path' => $guia->file_path]);
            abort(404, 'Archivo no encontrado');
        }
        $mime = $guia->mime_type ?: mime_content_type($fullPath);
        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($guia->file_path) . '"',
        ]);
    }

    /**
     * Devuelve el detalle de contabilidad para una cotización (vista VER).
     * Incluye: comprobantes, detracciones, guías de remisión, datos del panel lateral.
     *
     * GET /carga-consolidada/contenedor/factura-guia/contabilidad/detalle/{idCotizacion}
     */
    public function getContabilidadDetalle($idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
            }

            // Cargar comprobantes con su constancia de pago anidada; file_path siempre como URL absoluta firmada
            $comprobantes = Comprobante::where('quotation_id', $idCotizacion)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($item) {
                    $signedComprobanteUrl = !empty($item->file_path)
                        ? $this->absoluteSignedFileUrl('carga-consolidada.contabilidad.comprobante.file', ['id' => $item->id], now()->addMinutes(30))
                        : null;
                    $item->file_url  = $signedComprobanteUrl;
                    $item->file_path = $signedComprobanteUrl;

                    // Constancia de pago vinculada (solo si tiene detraccion)
                    $item->constancia = null;
                    if ($item->tiene_detraccion) {
                        $constancia = Detraccion::where('comprobante_id', $item->id)->first();
                        if ($constancia) {
                            $signedConstanciaUrl = !empty($constancia->file_path)
                                ? $this->absoluteSignedFileUrl('carga-consolidada.contabilidad.constancia.file', ['id' => $constancia->id], now()->addMinutes(30))
                                : null;
                            $constancia->file_url  = $signedConstanciaUrl;
                            $constancia->file_path = $signedConstanciaUrl;
                            $item->constancia = $constancia;
                        }
                    }

                    return $item;
                });

            $totalComprobantes = $comprobantes->sum('valor_comprobante');
            // Total detracciones = suma de montos declarados en soles (de los comprobantes)
            $totalDetracciones = $comprobantes
                ->where('tiene_detraccion', true)
                ->sum('monto_detraccion_soles');

            // Panel lateral: estado de documentos clave
            $guiasDb = GuiaRemision::where('quotation_id', $idCotizacion)->orderBy('id', 'desc')->get();
            $guiasRemision = $guiasDb->map(function ($g) {
                return [
                    'id' => $g->id,
                    'file_name' => $g->file_name ?? 'Guía',
                    'file_url' => !empty($g->file_path)
                        ? $this->absoluteSignedFileUrl('carga-consolidada.guia-remision.file', ['id' => $g->id], now()->addMinutes(30))
                        : null,
                ];
            })->values()->all();

            $legacyGuiaUrl = $cotizacion->guia_remision_url
                ? $this->generateImageUrl('cargaconsolidada/guiaremision/' . $idCotizacion . '/' . $cotizacion->guia_remision_url)
                : null;
            if (empty($guiasRemision) && $legacyGuiaUrl) {
                $guiasRemision = [['id' => 0, 'file_name' => $cotizacion->guia_remision_url, 'file_url' => $legacyGuiaUrl]];
            }

            $panel = [
                'tiene_cotizacion_inicial' => !empty($cotizacion->cotizacion_file_url),
                'tiene_cotizacion_final'   => !empty($cotizacion->cotizacion_final_url),
                'tiene_contrato'           => !empty($cotizacion->cotizacion_contrato_url),
                'cotizacion_inicial_url'   => !empty($cotizacion->cotizacion_file_url) ? $this->generateImageUrl($cotizacion->cotizacion_file_url) : null,
                'cotizacion_final_url'     => !empty($cotizacion->cotizacion_final_url) ? $this->generateImageUrl($cotizacion->cotizacion_final_url) : null,
                'contrato_url'             => !empty($cotizacion->cotizacion_contrato_url) ? $this->generateImageUrl($cotizacion->cotizacion_contrato_url) : null,
                'guia_remision_url'        => $legacyGuiaUrl,
                'guia_remision_file_name'  => $cotizacion->guia_remision_url,
                'guias_remision'           => $guiasRemision,
                'nota_contabilidad'        => $cotizacion->nota_contabilidad,
            ];

            // Datos básicos del cliente
            $cliente = [
                'id_cotizacion' => $cotizacion->id,
                'nombre'        => $cotizacion->nombre,
                'documento'     => $cotizacion->documento,
                'telefono'      => $cotizacion->telefono,
                'correo'        => $cotizacion->correo,
            ];

            return response()->json([
                'success'            => true,
                'cliente'            => $cliente,
                'comprobantes'       => $comprobantes,
                'total_comprobantes' => round($totalComprobantes, 2),
                'total_detracciones' => round($totalDetracciones, 2),
                'panel'              => $panel,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener detalle contabilidad', [
                'id_cotizacion' => $idCotizacion,
                'error'         => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error al obtener detalle: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Guarda la nota interna de contabilidad de una cotización.
     *
     * PUT /carga-consolidada/contenedor/factura-guia/contabilidad/nota/{idCotizacion}
     * Body (JSON): nota
     */
    public function saveNotaContabilidad(Request $request, $idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
            }

            $cotizacion->nota_contabilidad = $request->nota;
            $cotizacion->save();

            return response()->json(['success' => true, 'message' => 'Nota guardada correctamente']);
        } catch (\Exception $e) {
            Log::error('Error al guardar nota contabilidad', [
                'id_cotizacion' => $idCotizacion,
                'error'         => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error al guardar nota: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Envía el formulario de entrega por WhatsApp (instancia 'administracion') a los
     * clientes seleccionados de un contenedor.
     * Construye el link como APP_URL_CLIENTES + /formulario/{idCotizacion}
     *
     * POST /carga-consolidada/contenedor/factura-guia/contabilidad/enviar-formulario/{idContenedor}
     * Body (JSON): { cotizacion_ids: [1, 2, 3] }
     */
    public function enviarFormulario(Request $request, $idContenedor)
    {
        try {
            $cotizacionIds = $request->input('cotizacion_ids', []);

            if (empty($cotizacionIds)) {
                return response()->json(['success' => false, 'message' => 'Debe seleccionar al menos un cliente'], 400);
            }

            $clientesUrlBase = env('APP_URL_CLIENTES', 'http://localhost:3001');
            $enviados = [];
            $errores  = [];

            foreach ($cotizacionIds as $idCotizacion) {
                $cotizacion = Cotizacion::find($idCotizacion);
                if (!$cotizacion) {
                    $errores[] = ['id' => $idCotizacion, 'error' => 'Cotización no encontrada'];
                    continue;
                }

                $telefono = preg_replace('/\D+/', '', $cotizacion->telefono);
                if (empty($telefono)) {
                    $errores[] = ['id' => $idCotizacion, 'nombre' => $cotizacion->nombre, 'error' => 'Sin teléfono'];
                    continue;
                }

                if (strlen($telefono) < 9) {
                    $telefono = '51' . $telefono;
                }
                $numeroWhatsapp = $telefono . '@c.us';

                $cotizacion->loadMissing('contenedor');
                $datosFacturacion = $this->getDatosFacturacionParaMensaje($cotizacion);
                $message = $datosFacturacion
                    ? $this->buildMensajeFormularioAntiguo($cotizacion, $datosFacturacion)
                    : $this->buildMensajeFormularioNuevo($cotizacion, $idContenedor, $clientesUrlBase);

                $result = $this->sendMessage($message, $numeroWhatsapp, 0, 'administracion');

                if ($result && isset($result['status']) && $result['status']) {
                    $enviados[] = ['id' => $idCotizacion, 'nombre' => $cotizacion->nombre];
                } else {
                    $errores[] = ['id' => $idCotizacion, 'nombre' => $cotizacion->nombre, 'error' => 'Error al enviar WhatsApp'];
                }
            }

            return response()->json([
                'success'  => true,
                'enviados' => $enviados,
                'errores'  => $errores,
                'message'  => count($enviados) . ' mensaje(s) enviado(s) correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al enviar formularios por WhatsApp', [
                'id_contenedor' => $idContenedor,
                'error'         => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error al enviar formularios: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Devuelve lista de cotizaciones de un contenedor para el modal "Enviar formulario"
     * (nombre, telefono, si ya tiene formulario registrado).
     *
     * GET /carga-consolidada/contenedor/factura-guia/contabilidad/clientes/{idContenedor}
     */
    public function getClientesContenedor($idContenedor)
    {
        try {
            $cotizaciones = Cotizacion::select(
                'contenedor_consolidado_cotizacion.id',
                'contenedor_consolidado_cotizacion.nombre',
                'contenedor_consolidado_cotizacion.telefono',
                'contenedor_consolidado_cotizacion.correo',
                'contenedor_consolidado_cotizacion.documento',
                'contenedor_consolidado_cotizacion.delivery_form_registered_at',
                'contenedor_consolidado_cotizacion.registrado_comprobante_form',
                'contenedor_consolidado_cotizacion.id_contenedor_pago'
            )
                ->where('id_contenedor', $idContenedor)
                ->whereNotNull('estado_cliente')
                ->whereNull('id_cliente_importacion')
                ->where('estado_cotizador', 'CONFIRMADO')
                ->orderBy('nombre', 'asc')
                ->get()
                ->map(function ($item) {
                    $item->registrado = (bool) ($item->registrado_comprobante_form ?? false);
                    return $item;
                });

            return response()->json([
                'success' => true,
                'data'    => $cotizaciones,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener clientes del contenedor para enviar formulario', [
                'id_contenedor' => $idContenedor,
                'error'         => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Actualiza manualmente el estado "Registrado" de una cotización para contabilidad.
     * PUT /carga-consolidada/contenedor/factura-guia/contabilidad/registrado/{idCotizacion}
     */
    public function updateRegistradoComprobanteForm(Request $request, $idCotizacion)
    {
        try {
            $validated = $request->validate([
                'registrado' => 'required|boolean',
            ]);

            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
            }

            $cotizacion->registrado_comprobante_form = (bool) $validated['registrado'];
            $cotizacion->save();

            return response()->json([
                'success' => true,
                'message' => 'Estado registrado actualizado correctamente',
                'data' => [
                    'id_cotizacion' => (int) $cotizacion->id,
                    'registrado' => (bool) $cotizacion->registrado_comprobante_form,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('FacturaGuiaController@updateRegistradoComprobanteForm', [
                'id_cotizacion' => $idCotizacion,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error al actualizar estado registrado'], 500);
        }
    }

    /**
     * Envía por WhatsApp los comprobantes (factura/boleta) de una cotización al cliente.
     * POST /carga-consolidada/contenedor/factura-guia/contabilidad/send-comprobantes/{idCotizacion}
     */
    public function sendComprobantesContabilidad($idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['success' => false, 'error' => 'Cotización no encontrada'], 404);
            }

            $comprobantes = Comprobante::where('quotation_id', $idCotizacion)->orderBy('id')->get();
            if ($comprobantes->isEmpty()) {
                return response()->json(['success' => false, 'error' => 'No hay comprobantes para esta cotización'], 400);
            }

            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono);
            if (empty($telefono)) {
                return response()->json(['success' => false, 'error' => 'El cliente no tiene un número de teléfono válido'], 400);
            }
            if (strlen($telefono) < 9) {
                $telefono = '51' . $telefono;
            }
            $numeroWhatsapp = $telefono . '@c.us';

            $contenedor = Contenedor::find($cotizacion->id_contenedor);
            $carga = $contenedor ? $contenedor->carga : 'N/A';
            $mensajeInicial = "Hola " . $cotizacion->nombre . " 👋,\n\nTe enviamos los comprobantes de pago de tu consolidado #" . $carga . ".";

            $enviados = 0;
            $errores = [];
            foreach ($comprobantes as $idx => $c) {
                if (empty($c->file_path)) {
                    continue;
                }
                $fullPath = storage_path('app/' . $c->file_path);
                if (!is_file($fullPath)) {
                    $errores[] = $c->file_name ?? 'Comprobante';
                    continue;
                }
                $mimeType = $c->mime_type ?: mime_content_type($fullPath);
                $message = $idx === 0 ? $mensajeInicial : null;
                $result = $this->sendMedia($fullPath, $mimeType, $message, $numeroWhatsapp, 1, 'administracion', $c->file_name ?? null);
                if ($result && is_array($result) && !empty($result['status'])) {
                    $enviados++;
                } else {
                    $errores[] = $c->file_name ?? 'Comprobante';
                }
            }

            if ($enviados === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo enviar ningún comprobante' . (count($errores) ? ': ' . implode(', ', $errores) : ''),
                ], 500);
            }
            return response()->json([
                'success' => true,
                'message' => $enviados . ' comprobante(s) enviado(s) correctamente por WhatsApp',
                'data' => ['enviados' => $enviados, 'errores' => $errores],
            ]);
        } catch (\Exception $e) {
            Log::error('sendComprobantesContabilidad: ' . $e->getMessage(), ['id_cotizacion' => $idCotizacion]);
            return response()->json(['success' => false, 'error' => 'Error al enviar comprobantes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Envía por WhatsApp las guías de remisión de una cotización al cliente.
     * POST /carga-consolidada/contenedor/factura-guia/contabilidad/send-guias/{idCotizacion}
     */
    public function sendGuiasContabilidad($idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['success' => false, 'error' => 'Cotización no encontrada'], 404);
            }

            $guias = GuiaRemision::where('quotation_id', $idCotizacion)->orderBy('id')->get();
            $legacyPath = null;
            if ($guias->isEmpty() && !empty($cotizacion->guia_remision_url)) {
                $legacyPath = storage_path('app/cargaconsolidada/guiaremision/' . $idCotizacion . '/' . $cotizacion->guia_remision_url);
                if (!is_file($legacyPath)) {
                    $legacyPath = null;
                }
            }
            if ($guias->isEmpty() && !$legacyPath) {
                return response()->json(['success' => false, 'error' => 'No hay guías de remisión para esta cotización'], 400);
            }

            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono);
            if (empty($telefono)) {
                return response()->json(['success' => false, 'error' => 'El cliente no tiene un número de teléfono válido'], 400);
            }
            if (strlen($telefono) < 9) {
                $telefono = '51' . $telefono;
            }
            $numeroWhatsapp = $telefono . '@c.us';

            $contenedor = Contenedor::find($cotizacion->id_contenedor);
            $carga = $contenedor ? $contenedor->carga : 'N/A';
            $mensajeInicial = "Hola " . $cotizacion->nombre . " 😊,\n\nTe enviamos tu(s) guía(s) de remisión del consolidado #" . $carga . ".";

            $enviados = 0;
            if ($legacyPath) {
                $mimeType = mime_content_type($legacyPath);
                $result = $this->sendMedia($legacyPath, $mimeType, $mensajeInicial, $numeroWhatsapp, 0, 'administracion', $cotizacion->guia_remision_url);
                if ($result && is_array($result) && !empty($result['status'])) {
                    $enviados++;
                }
            } else {
                foreach ($guias as $idx => $g) {
                    if (empty($g->file_path)) {
                        continue;
                    }
                    $fullPath = storage_path('app/' . $g->file_path);
                    if (!is_file($fullPath)) {
                        continue;
                    }
                    $mimeType = $g->mime_type ?: mime_content_type($fullPath);
                    $message = $idx === 0 ? $mensajeInicial : null;
                    $result = $this->sendMedia($fullPath, $mimeType, $message, $numeroWhatsapp, 1, 'administracion', $g->file_name ?? null);
                    if ($result && is_array($result) && !empty($result['status'])) {
                        $enviados++;
                    }
                }
            }

            if ($enviados === 0) {
                return response()->json(['success' => false, 'error' => 'No se pudo enviar ninguna guía por WhatsApp'], 500);
            }
            return response()->json([
                'success' => true,
                'message' => $enviados . ' guía(s) enviada(s) correctamente por WhatsApp',
                'data' => ['enviados' => $enviados],
            ]);
        } catch (\Exception $e) {
            Log::error('sendGuiasContabilidad: ' . $e->getMessage(), ['id_cotizacion' => $idCotizacion]);
            return response()->json(['success' => false, 'error' => 'Error al enviar guías: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Envía por WhatsApp las constancias de detracción de una cotización al cliente.
     * POST /carga-consolidada/contenedor/factura-guia/contabilidad/send-detracciones/{idCotizacion}
     */
    public function sendDetraccionesContabilidad($idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['success' => false, 'error' => 'Cotización no encontrada'], 404);
            }

            $comprobantesConDetraccion = Comprobante::where('quotation_id', $idCotizacion)->where('tiene_detraccion', true)->orderBy('id')->get();
            $constancias = [];
            foreach ($comprobantesConDetraccion as $c) {
                $det = Detraccion::where('comprobante_id', $c->id)->first();
                if ($det && !empty($det->file_path)) {
                    $constancias[] = $det;
                }
            }
            if (empty($constancias)) {
                return response()->json(['success' => false, 'error' => 'No hay constancias de detracción para esta cotización'], 400);
            }

            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono);
            if (empty($telefono)) {
                return response()->json(['success' => false, 'error' => 'El cliente no tiene un número de teléfono válido'], 400);
            }
            if (strlen($telefono) < 9) {
                $telefono = '51' . $telefono;
            }
            $numeroWhatsapp = $telefono . '@c.us';

            $contenedor = Contenedor::find($cotizacion->id_contenedor);
            $carga = $contenedor ? $contenedor->carga : 'N/A';
            $mensajeInicial = "Hola " . $cotizacion->nombre . " 👋,\n\nTe enviamos las constancias de pago de detracción de tu consolidado #" . $carga . ".";

            $enviados = 0;
            foreach ($constancias as $idx => $det) {
                $fullPath = storage_path('app/' . $det->file_path);
                if (!is_file($fullPath)) {
                    continue;
                }
                $mimeType = $det->mime_type ?: mime_content_type($fullPath);
                $message = $idx === 0 ? $mensajeInicial : null;
                $result = $this->sendMedia($fullPath, $mimeType, $message, $numeroWhatsapp, 1, 'administracion', $det->file_name ?? null);
                if ($result && is_array($result) && !empty($result['status'])) {
                    $enviados++;
                }
            }

            if ($enviados === 0) {
                return response()->json(['success' => false, 'error' => 'No se pudo enviar ninguna constancia por WhatsApp'], 500);
            }
            return response()->json([
                'success' => true,
                'message' => $enviados . ' constancia(s) de detracción enviada(s) correctamente por WhatsApp',
                'data' => ['enviados' => $enviados],
            ]);
        } catch (\Exception $e) {
            Log::error('sendDetraccionesContabilidad: ' . $e->getMessage(), ['id_cotizacion' => $idCotizacion]);
            return response()->json(['success' => false, 'error' => 'Error al enviar detracciones: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Envía por WhatsApp el enlace al formulario de comprobante para una cotización.
     * POST /carga-consolidada/contenedor/factura-guia/contabilidad/send-formulario/{idCotizacion}
     */
    public function sendFormularioContabilidad($idCotizacion)
    {
        try {
            $cotizacion = Cotizacion::with('contenedor')->find($idCotizacion);
            if (!$cotizacion) {
                return response()->json(['success' => false, 'error' => 'Cotización no encontrada'], 404);
            }

            $telefono = preg_replace('/\D+/', '', $cotizacion->telefono);
            if (empty($telefono)) {
                return response()->json(['success' => false, 'error' => 'El cliente no tiene un número de teléfono válido'], 400);
            }
            if (strlen($telefono) < 9) {
                $telefono = '51' . $telefono;
            }
            $numeroWhatsapp = $telefono . '@c.us';

            $idContenedor = $cotizacion->id_contenedor;
            $clientesUrlBase = env('APP_URL_CLIENTES', 'http://localhost:3001');
            $datosFacturacion = $this->getDatosFacturacionParaMensaje($cotizacion);
            $message = $datosFacturacion
                ? $this->buildMensajeFormularioAntiguo($cotizacion, $datosFacturacion)
                : $this->buildMensajeFormularioNuevo($cotizacion, $idContenedor, $clientesUrlBase);

            $result = $this->sendMessage($message, $numeroWhatsapp, 0, 'administracion');

            if ($result && isset($result['status']) && $result['status']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Formulario enviado correctamente por WhatsApp',
                    'data' => ['messageId' => $result['response']['messageId'] ?? null, 'sentAt' => now()->toISOString()],
                ]);
            }
            $errorMessage = $result['response']['error'] ?? ($result['error'] ?? 'Error desconocido');
            return response()->json(['success' => false, 'error' => 'Error al enviar formulario por WhatsApp: ' . $errorMessage], 500);
        } catch (\Exception $e) {
            Log::error('sendFormularioContabilidad: ' . $e->getMessage(), ['id_cotizacion' => $idCotizacion]);
            return response()->json(['success' => false, 'error' => 'Error al enviar formulario: ' . $e->getMessage()], 500);
        }
    }
}
