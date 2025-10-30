<?php

namespace App\Http\Controllers\CargaConsolidada\Clientes;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;
use Exception;

class EmbarcadosController extends Controller
{
    private $table_contenedor_cotizacion = "contenedor_consolidado_cotizacion";
    private $table_contenedor_cotizacion_proveedores = "contenedor_consolidado_cotizacion_proveedores";
    private $table_contenedor_consolidado_cotizacion_coordinacion_pagos = "contenedor_consolidado_cotizacion_coordinacion_pagos";
    private $table_pagos_concept = "cotizacion_coordinacion_pagos_concept";
    private $table = "carga_consolidada_contenedor";
    /**
     * Obtener la lista de embarcados para un contenedor específico.
     *
     * @param int $idContenedor
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmbarcados($idContenedor)
    {
        try {
            // Basado en el índice de GeneralController: traer cotizaciones mediante query builder
            $cotizaciones = DB::table('contenedor_consolidado_cotizacion as CC')
                ->leftJoin('contenedor_consolidado_tipo_cliente as TC', 'TC.id', '=', 'CC.id_tipo_cliente')
                ->select([
                    'CC.id',
                    'CC.nombre',
                    'CC.telefono',
                    'TC.name as tipo_cliente'
                ])
                ->where('CC.id_contenedor', $idContenedor)
                ->whereNotNull('CC.estado_cliente')
                ->whereNull('CC.id_cliente_importacion')
                ->where('CC.estado_cotizador', 'CONFIRMADO')
                ->get();

            if ($cotizaciones->isEmpty()) {
                Log::info("getEmbarcados: no hay cotizaciones embarcadas para contenedor={$idContenedor}");
                return response()->json(['status' => 'success', 'data' => []]);
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
                    'excel_confirmacion'
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

            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            Log::error('Error al obtener embarcados: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener embarcados.'
            ], 500);
        }
    }

    /**
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
                    $normalized = preg_replace('#^/storage/#', '', $path);
                    if (Storage::disk('public')->exists($normalized)) {
                        Storage::disk('public')->delete($normalized);
                    }
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
                    $normalized = preg_replace('#^/storage/#', '', $path);
                    if (Storage::disk('public')->exists($normalized)) {
                        Storage::disk('public')->delete($normalized);
                    }
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
                    $normalized = preg_replace('#^/storage/#', '', $path);
                    if (Storage::disk('public')->exists($normalized)) {
                        Storage::disk('public')->delete($normalized);
                    }
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
                $normalized = preg_replace('#^/storage/#', '', $current);
                if (Storage::disk('public')->exists($normalized)) {
                    Storage::disk('public')->delete($normalized);
                }
            }

            $stored = Storage::disk('public')->putFileAs('assets/images/agentecompra', $file, $filename);
            $prov->factura_comercial = $stored; // ruta relativa dentro del disk 'public'
            $prov->save();

            return response()->json(['status' => 'success', 'message' => 'Factura comercial subida correctamente', 'path' => $stored, 'url' => $this->generateImageUrl($stored)]);
        } catch (Exception $e) {
            Log::error('Error al subir factura comercial: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Error al subir factura comercial'], 500);
        }
    }

    /**
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
                $normalized = preg_replace('#^/storage/#', '', $current);
                if (Storage::disk('public')->exists($normalized)) {
                    Storage::disk('public')->delete($normalized);
                }
            }

            $stored = Storage::disk('public')->putFileAs('assets/images/agentecompra', $file, $filename);
            $prov->packing_list = $stored;
            $prov->save();

            return response()->json(['status' => 'success', 'message' => 'Packing list subida correctamente', 'path' => $stored, 'url' => $this->generateImageUrl($stored)]);
        } catch (Exception $e) {
            Log::error('Error al subir packing list: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Error al subir packing list'], 500);
        }
    }

    /**
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
                $normalized = preg_replace('#^/storage/#', '', $current);
                if (Storage::disk('public')->exists($normalized)) {
                    Storage::disk('public')->delete($normalized);
                }
            }

            $stored = Storage::disk('public')->putFileAs('assets/images/agentecompra', $file, $filename);
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
     * Convierte una ruta relativa de archivo en una URL completa.
     * Copiado del comportamiento usado en otros controllers para mantener consistencia.
     */
    private function generateImageUrl($ruta)
    {
        if (empty($ruta)) {
            return null;
        }

        // Si ya es una URL completa, devolverla tal como está
        if (filter_var($ruta, FILTER_VALIDATE_URL)) {
            return $ruta;
        }

        // Limpiar partes y unir sin producir dobles slashes
        $baseUrl = config('app.url') ?? '';
        $storagePath = '/storage/';

        // Normalizar para que no queden slashes extras
        $baseUrl = rtrim($baseUrl, '/');
        $storagePath = trim($storagePath, '/');
        $ruta = ltrim($ruta, '/');

        // Unir con '/' garantizando una sola barra entre segmentos
        return implode('/', array_filter([$baseUrl, $storagePath, $ruta], fn($s) => $s !== ''));
    }
}