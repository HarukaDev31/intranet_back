<?php

namespace App\Http\Controllers\CargaConsolidada\Clientes;
use App\Http\Controllers\Controller;
use App\Traits\FileTrait;
use App\Traits\UsesObjectStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\Usuario;
use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveService;
use Illuminate\Support\Facades\Log;
use Exception;

class EmbarcadosController extends Controller
{
    use FileTrait;
    use UsesObjectStorage;
    private $table_contenedor_cotizacion = "contenedor_consolidado_cotizacion";
    private $table_contenedor_cotizacion_proveedores = "contenedor_consolidado_cotizacion_proveedores";
    private $table_contenedor_consolidado_cotizacion_coordinacion_pagos = "contenedor_consolidado_cotizacion_coordinacion_pagos";
    private $table_pagos_concept = "cotizacion_coordinacion_pagos_concept";
    private $table = "carga_consolidada_contenedor";
    
    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedores/{idContenedor}/clientes/embarcados",
     *     tags={"Clientes Carga Consolidada"},
     *     summary="Obtener embarcados de un contenedor",
     *     description="Obtiene la lista de clientes embarcados para un contenedor específico",
     *     operationId="getEmbarcados",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idContenedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="currentPage", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="itemsPerPage", in="query", @OA\Schema(type="integer", default=100)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Embarcados obtenidos exitosamente")
     * )
     *
     * Obtener la lista de embarcados para un contenedor específico.
     *
     * @param int $idContenedor
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmbarcados(Request $request, $idContenedor)
    {
        try {
            // Basado en el índice de GeneralController: traer cotizaciones mediante query builder
            $page = max(1, (int) $request->input('currentPage', 1));
            $perPage = (int) $request->input('itemsPerPage', 100);
            $search = trim((string) $request->input('search', ''));

            $baseQuery = DB::table('contenedor_consolidado_cotizacion as CC')
                ->leftJoin('contenedor_consolidado_tipo_cliente as TC', 'TC.id', '=', 'CC.id_tipo_cliente')
                ->select([
                    'CC.id',
                    'CC.nombre',
                    'CC.telefono',
                    'TC.name as tipo_cliente'
                ])
                ->where('CC.id_contenedor', $idContenedor)
                ->whereNull('CC.deleted_at')
                ->whereNotNull('CC.estado_cliente')
                ->whereNull('CC.id_cliente_importacion')
                ->where('CC.estado_cotizador', 'CONFIRMADO')
                ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion_proveedores')
                    ->whereColumn('contenedor_consolidado_cotizacion_proveedores.id_cotizacion', 'CC.id');
            });

            if ($search !== '') {
                $like = "%{$search}%";
                $baseQuery->where(function ($q) use ($like) {
                    $q->where('CC.nombre', 'like', $like)
                      ->orWhere('CC.telefono', 'like', $like)
                      ->orWhere('CC.documento', 'like', $like);
                });
            }

            $cotizacionesPage = $baseQuery->orderBy('CC.id', 'asc')->paginate($perPage, ['CC.id','CC.nombre','CC.telefono','TC.name as tipo_cliente'], 'page', $page);
            $cotizaciones = collect($cotizacionesPage->items());

            if ($cotizaciones->isEmpty()) {
                Log::info("getEmbarcados: no hay cotizaciones embarcadas para contenedor={$idContenedor}");
                return response()->json(['status' => 'success', 'data' => [], 'pagination' => [
                    'current_page' => $cotizacionesPage->currentPage(),
                    'per_page' => $cotizacionesPage->perPage(),
                    'total' => $cotizacionesPage->total(),
                    'last_page' => $cotizacionesPage->lastPage(),
                ]]);
            }

            $ids = $cotizaciones->pluck('id')->all();

            // Obtener proveedores relacionados en una sola consulta
            $proveedores = DB::table('contenedor_consolidado_cotizacion_proveedores')
                ->whereIn('id_cotizacion', $ids)
                ->where('id_contenedor', $idContenedor)
                ->select([
                    'id',
                    'id_cotizacion',
                    'products',
                    'supplier',
                    'code_supplier',
                    'cbm_total as vol_peru',
                    'cbm_total_china as vol_china',
                    'factura_comercial',
                    'packing_list',
                    'excel_confirmacion',
                    'invoice_status',
                    'packing_status',
                    'excel_conf_status',
                ])
                ->get()
                ->groupBy('id_cotizacion');

            $data = collect($cotizaciones)->map(function ($cot) use ($proveedores) {
                // Devolver el teléfono tal como está en la BD (sin formatear)
                $whatsapp = $cot->telefono ?? '';

                $provList = [];
                if (isset($proveedores[$cot->id])) {
                    $provList = collect($proveedores[$cot->id])->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'products' => $p->products,
                            'supplier' => $p->supplier,
                            'code_supplier' => $p->code_supplier,
                            'vol_peru' => $p->vol_peru,
                            'vol_china' => $p->vol_china,
                            // Devolver URLs completas para los archivos si existen
                            'factura_comercial' => $this->generateImageUrl($p->factura_comercial),
                            'packing_list' => $this->generateImageUrl($p->packing_list),
                            'excel_confirmacion' => $this->generateImageUrl($p->excel_confirmacion),
                            //Devolver status de documentos
                            'invoice_status' => $p->invoice_status,
                            'packing_status' => $p->packing_status,
                            'excel_conf_status' => $p->excel_conf_status,
                        ];
                    })->values();
                }

                return [
                    'id' => $cot->id,
                    'nombre' => $cot->nombre,
                    'whatsapp' => $whatsapp,
                    'tipo_cliente' => $cot->tipo_cliente ?? null,
                    'proveedores' => $provList
                ];
            })->values();

            return response()->json(['status' => 'success', 'data' => $data, 'pagination' => [
                'current_page' => $cotizacionesPage->currentPage(),
                'per_page' => $cotizacionesPage->perPage(),
                'total' => $cotizacionesPage->total(),
                'last_page' => $cotizacionesPage->lastPage(),
            ]]);
        } catch (Exception $e) {
            Log::error('Error al obtener embarcados: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener embarcados.'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/carga-consolidada/contenedor/clientes/embarcados/{idProveedor}/factura-comercial",
     *     tags={"Clientes Carga Consolidada"},
     *     summary="Eliminar factura comercial del proveedor",
     *     description="Elimina la factura comercial de un proveedor",
     *     operationId="deleteFacturaComercialEmb",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idProveedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Factura eliminada exitosamente"),
     *     @OA\Response(response=404, description="Proveedor no encontrado")
     * )
     *
     * Elimina la factura comercial de un proveedor (borra archivo en storage si aplica y deja el campo en null).
     * @param int $idProveedor
     */
    public function deleteFacturaComercial($idProveedor)
    {
        try {
            $prov = CotizacionProveedor::find($idProveedor);
            if (!$prov) {
                return response()->json(['status' => 'error', 'message' => 'Proveedor no encontrado'], 404);
            }

            $path = $prov->factura_comercial;
            if (!empty($path)) {
                // Si no es una URL absoluta intentamos borrar del disco 'public'
                if (!filter_var($path, FILTER_VALIDATE_URL)) {
                    // Normalizar: quitar prefijo /storage/ si lo tiene
                    $this->deleteStoredFile($path);
                }
                $prov->factura_comercial = null;
                $prov->save();
            }

            return response()->json(['status' => 'success', 'message' => 'Factura comercial eliminada correctamente']);
        } catch (Exception $e) {
            Log::error('Error al eliminar factura comercial: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Error al eliminar factura comercial'], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/carga-consolidada/contenedor/clientes/embarcados/{idProveedor}/packing-list",
     *     tags={"Clientes Carga Consolidada"},
     *     summary="Eliminar packing list del proveedor",
     *     description="Elimina el packing list de un proveedor",
     *     operationId="deletePackingListEmb",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idProveedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Packing list eliminado exitosamente"),
     *     @OA\Response(response=404, description="Proveedor no encontrado")
     * )
     *
     * Elimina el packing list de un proveedor (borra archivo en storage si aplica y deja el campo en null).
     * @param int $idProveedor
     */
    public function deletePackingList($idProveedor)
    {
        try {
            $prov = CotizacionProveedor::find($idProveedor);
            if (!$prov) {
                return response()->json(['status' => 'error', 'message' => 'Proveedor no encontrado'], 404);
            }

            $path = $prov->packing_list;
            if (!empty($path)) {
                if (!filter_var($path, FILTER_VALIDATE_URL)) {
                    $this->deleteStoredFile($path);
                }
                $prov->packing_list = null;
                $prov->save();
            }

            return response()->json(['status' => 'success', 'message' => 'Packing list eliminada correctamente']);
        } catch (Exception $e) {
            Log::error('Error al eliminar packing list: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Error al eliminar packing list'], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/carga-consolidada/contenedor/clientes/embarcados/{idProveedor}/excel-confirmacion",
     *     tags={"Clientes Carga Consolidada"},
     *     summary="Eliminar excel de confirmación del proveedor",
     *     description="Elimina el excel de confirmación de un proveedor",
     *     operationId="deleteExcelConfirmacionEmb",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idProveedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Excel eliminado exitosamente"),
     *     @OA\Response(response=404, description="Proveedor no encontrado")
     * )
     *
     * Elimina el excel de confirmación de un proveedor (borra archivo en storage si aplica y deja el campo en null).
     * @param int $idProveedor
     */
    public function deleteExcelConfirmacion($idProveedor)
    {
        try {
            $prov = CotizacionProveedor::find($idProveedor);
            if (!$prov) {
                return response()->json(['status' => 'error', 'message' => 'Proveedor no encontrado'], 404);
            }

            $path = $prov->excel_confirmacion;
            if (!empty($path)) {
                if (!filter_var($path, FILTER_VALIDATE_URL)) {
                    $this->deleteStoredFile($path);
                }
                $prov->excel_confirmacion = null;
                $prov->save();
            }

            return response()->json(['status' => 'success', 'message' => 'Excel de confirmación eliminado correctamente']);
        } catch (Exception $e) {
            Log::error('Error al eliminar excel de confirmación: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Error al eliminar excel de confirmación'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor/clientes/embarcados/{idProveedor}/factura-comercial",
     *     tags={"Clientes Carga Consolidada"},
     *     summary="Subir factura comercial del proveedor",
     *     description="Sube un archivo de factura comercial para un proveedor",
     *     operationId="uploadFacturaComercialEmb",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idProveedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Factura subida exitosamente"),
     *     @OA\Response(response=404, description="Proveedor no encontrado")
     * )
     *
     * Sube la factura comercial para un proveedor, almacena el archivo en disk 'public' y actualiza la BD.
     * Si ya existía un archivo local, lo elimina antes de guardar el nuevo.
     * @param Request $request
     * @param int $idProveedor
     */
    public function uploadFacturaComercial(Request $request, $idProveedor)
    {
        try {
            $prov = CotizacionProveedor::find($idProveedor);
            if (!$prov) {
                return response()->json(['status' => 'error', 'message' => 'Proveedor no encontrado'], 404);
            }

            $validator = \Validator::make($request->all(), [
                'file' => 'required|file|mimes:pdf,xls,xlsx,doc,docx|max:10240'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
            }

            $file = $request->file('file');
            $original = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $file->getClientOriginalName());
            $filename = time() . '_' . $original;

            // Borrar archivo previo si era local
            $current = $prov->factura_comercial;
            if (!empty($current) && !filter_var($current, FILTER_VALIDATE_URL)) {
                $this->deleteStoredFile($current);
            }

            $stored = $this->storageStoreUpload($file, 'assets/images/agentecompra', $filename);
            $prov->factura_comercial = $stored; // ruta relativa dentro del disk 'public'
            $prov->save();

            return response()->json(['status' => 'success', 'message' => 'Factura comercial subida correctamente', 'path' => $stored, 'url' => $this->generateImageUrl($stored)]);
        } catch (Exception $e) {
            Log::error('Error al subir factura comercial: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Error al subir factura comercial'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor/clientes/embarcados/{idProveedor}/packing-list",
     *     tags={"Clientes Carga Consolidada"},
     *     summary="Subir packing list del proveedor",
     *     description="Sube un archivo de packing list para un proveedor",
     *     operationId="uploadPackingListEmb",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idProveedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Packing list subido exitosamente"),
     *     @OA\Response(response=404, description="Proveedor no encontrado")
     * )
     *
     * Sube el packing list para un proveedor, almacena el archivo en disk 'public' y actualiza la BD.
     */
    public function uploadPackingList(Request $request, $idProveedor)
    {
        try {
            $prov = CotizacionProveedor::find($idProveedor);
            if (!$prov) {
                return response()->json(['status' => 'error', 'message' => 'Proveedor no encontrado'], 404);
            }

            $validator = \Validator::make($request->all(), [
                'file' => 'required|file|mimes:pdf,xls,xlsx|max:10240'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
            }

            $file = $request->file('file');
            $original = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $file->getClientOriginalName());
            $filename = time() . '_' . $original;

            // Borrar previo si era local
            $current = $prov->packing_list;
            if (!empty($current) && !filter_var($current, FILTER_VALIDATE_URL)) {
                $this->deleteStoredFile($current);
            }

            $stored = $this->storageStoreUpload($file, 'assets/images/agentecompra', $filename);
            $prov->packing_list = $stored;
            $prov->save();

            return response()->json(['status' => 'success', 'message' => 'Packing list subida correctamente', 'path' => $stored, 'url' => $this->generateImageUrl($stored)]);
        } catch (Exception $e) {
            Log::error('Error al subir packing list: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Error al subir packing list'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor/clientes/embarcados/{idProveedor}/excel-confirmacion",
     *     tags={"Clientes Carga Consolidada"},
     *     summary="Subir excel de confirmación del proveedor",
     *     description="Sube un archivo excel de confirmación para un proveedor",
     *     operationId="uploadExcelConfirmacionEmb",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idProveedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Excel subido exitosamente"),
     *     @OA\Response(response=404, description="Proveedor no encontrado")
     * )
     *
     * Sube el excel de confirmación para un proveedor, almacena el archivo en disk 'public' y actualiza la BD.
     */
    public function uploadExcelConfirmacion(Request $request, $idProveedor)
    {
        try {
            $prov = CotizacionProveedor::find($idProveedor);
            if (!$prov) {
                return response()->json(['status' => 'error', 'message' => 'Proveedor no encontrado'], 404);
            }

            $validator = \Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls|max:10240'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
            }

            $file = $request->file('file');
            $original = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $file->getClientOriginalName());
            $filename = time() . '_' . $original;

            // Borrar previo si era local
            $current = $prov->excel_confirmacion;
            if (!empty($current) && !filter_var($current, FILTER_VALIDATE_URL)) {
                $this->deleteStoredFile($current);
            }

            $stored = $this->storageStoreUpload($file, 'assets/images/agentecompra', $filename);
            $prov->excel_confirmacion = $stored;
            $prov->save();

            return response()->json(['status' => 'success', 'message' => 'Excel de confirmación subido correctamente', 'path' => $stored, 'url' => $this->generateImageUrl($stored)]);
        } catch (Exception $e) {
            Log::error('Error al subir excel de confirmación: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Error al subir excel de confirmación'], 500);
        }
    }
    public function getHeadersData($idContenedor)
    {
        $headers = DB::table($this->table_contenedor_cotizacion)
            ->select([
                DB::raw('SUM(qty_items) as total_qty_items'),
                DB::raw('SUM(total_logistica) as total_logistica'),
                DB::raw('SUM(total_logistica_pagado) as total_logistica_pagado'),
            ])
            ->where('id_contenedor', $idContenedor)
            ->whereNotNull('estado_cliente')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->whereNull('id_cliente_importacion')
            ->first();
        // Attach formatted totals alongside numeric values
        if ($headers) {
            $headers->total_logistica_formatted = $this->formatCurrency($headers->total_logistica ?? 0);
            $headers->total_logistica_pagado_formatted = $this->formatCurrency($headers->total_logistica_pagado ?? 0);
        }
        return response()->json([
            'data' => $headers,
            'success' => true
        ]);
    }

    /**
     * Vincula el Excel de seguimiento al Google Drive del consolidado.
     *
     * @param Request $request
     * @param int $idContenedor
     * @param SeguimientoConsolidadoDriveService $driveService
     */
    public function vincularDriveSeguimiento(Request $request, $idContenedor, SeguimientoConsolidadoDriveService $driveService)
    {
        if (!$this->canManageDriveSeguimiento()) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
        }

        Log::info('[SeguimientoDrive] POST vincular-drive', [
            'id_contenedor' => (int) $idContenedor,
            'user_id' => auth()->id(),
        ]);

        $result = $driveService->queueVincular((int) $idContenedor);

        if (!empty($result['success']) && !empty($result['queued'])) {
            return response()->json($result, 202);
        }

        if (!empty($result['success'])) {
            return response()->json($result, 200);
        }

        $httpStatus = 422;
        if (!empty($result['data']['processing'])) {
            $httpStatus = 409;
        }

        return response()->json($result, $httpStatus);
    }

    /**
     * Solo cotizador que no sea jefe de ventas.
     *
     * @return bool
     */
    private function canManageDriveSeguimiento()
    {
        return SeguimientoConsolidadoDriveService::userCanManageDriveSeguimiento(auth()->user());
    }

    /**
     * Configuración global Excel seguimiento Drive (hora de corte CONTACTAR).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSeguimientoDriveConfig()
    {
        if (!$this->canManageDriveSeguimiento()) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => \App\Services\CargaConsolidada\SeguimientoConsolidadoCorteConfig::toPublicArray(),
        ]);
    }

    /**
     * @param Request $request
     * @param \App\Services\SystemConfigService $systemConfigService
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSeguimientoDriveConfig(Request $request, \App\Services\SystemConfigService $systemConfigService)
    {
        if (!$this->canManageDriveSeguimiento()) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
        }

        $request->validate([
            'hora_corte' => ['required', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $hora = trim((string) $request->input('hora_corte'));
        $systemConfigService->set(
            \App\Services\SystemConfigService::KEY_EXCEL_SEGUIMIENTO_HORA_CORTE,
            $hora
        );

        $timezone = trim((string) $request->input('timezone', ''));
        if ($timezone !== '') {
            $systemConfigService->set(
                \App\Services\SystemConfigService::KEY_EXCEL_SEGUIMIENTO_TIMEZONE,
                $timezone
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Hora de corte actualizada.',
            'data' => \App\Services\CargaConsolidada\SeguimientoConsolidadoCorteConfig::toPublicArray(),
        ]);
    }

    private function deleteStoredFile(?string $path): void
    {
        if (empty($path) || filter_var($path, FILTER_VALIDATE_URL)) {
            return;
        }
        $normalized = $this->objectStorage()->normalizeRelativePath(preg_replace('#^/storage/#', '', $path));
        if ($normalized && $this->objectStorage()->exists($normalized)) {
            $this->objectStorage()->delete($normalized);
        }
    }
}