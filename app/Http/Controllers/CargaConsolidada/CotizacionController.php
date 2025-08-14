<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CotizacionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = Cotizacion::query();

            // Aplicar filtros básicos
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nombre', 'LIKE', "%{$search}%")
                      ->orWhere('documento', 'LIKE', "%{$search}%")
                      ->orWhere('telefono', 'LIKE', "%{$search}%");
                });
            }

            // Filtrar por estado si se proporciona
            if ($request->has('estado') && !empty($request->estado)) {
                $query->where('estado', $request->estado);
            }

            // Filtrar por fecha si se proporciona
            if ($request->has('fecha_inicio')) {
                $query->whereDate('fecha', '>=', $request->fecha_inicio);
            }
            if ($request->has('fecha_fin')) {
                $query->whereDate('fecha', '<=', $request->fecha_fin);
            }

            // Aplicar filtros según el rol del usuario
            switch ($user->rol) {
                case Usuario::ROL_COTIZADOR:
                    if ($user->id_usuario != 28791) { 
                        $query->where('id_usuario', $user->id_usuario);
                    }
                    $query->where('estado_cotizador', 'CONFIRMADO');
                    break;

                case Usuario::ROL_DOCUMENTACION:
                    $query->where('estado_cotizador', 'CONFIRMADO')
                          ->whereNotNull('estado_cliente');
                    break;

                case Usuario::ROL_COORDINACION:
                    $query->where('estado_cotizador', 'CONFIRMADO')
                          ->whereNotNull('estado_cliente');
                    break;
            }

            // Ordenamiento
            $sortField = $request->input('sort_by', 'fecha');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortField, $sortOrder);

            // Paginación
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            $results = $query->paginate($perPage, ['*'], 'page', $page);

            // Transformar los datos para la respuesta
            $data = $results->map(function ($cotizacion) {
                return [
                    'id' => $cotizacion->id,
                    'nombre' => $cotizacion->nombre,
                    'documento' => $cotizacion->documento,
                    'telefono' => $cotizacion->telefono,
                    'correo' => $cotizacion->correo,
                    'fecha' => $cotizacion->fecha,
                    'estado' => $cotizacion->estado,
                    'estado_cliente' => $cotizacion->estado_cliente,
                    'estado_cotizador' => $cotizacion->estado_cotizador,
                    'monto' => $cotizacion->monto,
                    'monto_final' => $cotizacion->monto_final,
                    'volumen' => $cotizacion->volumen,
                    'volumen_final' => $cotizacion->volumen_final
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en index de cotizaciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cotizaciones: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Implementación básica
        return response()->json(['message' => 'Cotizacion store']);
    }

    public function show($id)
    {
        // Implementación básica
        return response()->json(['message' => 'Cotizacion show']);
    }

    public function update(Request $request, $id)
    {
        // Implementación básica
        return response()->json(['message' => 'Cotizacion update']);
    }

    public function destroy($id)
    {
        // Implementación básica
        return response()->json(['message' => 'Cotizacion destroy']);
    }

    public function filterOptions()
    {
        // Implementación básica
        return response()->json(['message' => 'Cotizacion filter options']);
    }

    /**
     * Obtener documentación de clientes para una cotización específica
     * Replica la funcionalidad del método showClientesDocumentacion de CodeIgniter
     */
    public function showClientesDocumentacion($id)
    {
        try {
            // Obtener la cotización principal con todas sus relaciones
            $cotizacion = Cotizacion::with([
                'documentacion',
                'proveedores',
                'documentacionAlmacen',
                'inspeccionAlmacen'
            ])
            ->where('id', $id)
            ->whereNotNull('estado')
            ->first();

            if (!$cotizacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotización no encontrada'
                ], 404);
            }

            // Debug: Verificar qué datos se obtienen
            Log::info('Cotización encontrada:', ['id' => $cotizacion->id, 'estado' => $cotizacion->estado]);
            Log::info('Documentación count:', ['count' => $cotizacion->documentacion->count()]);
            Log::info('Proveedores count:', ['count' => $cotizacion->proveedores->count()]);
            Log::info('Documentación almacén count:', ['count' => $cotizacion->documentacionAlmacen->count()]);
            Log::info('Inspección almacén count:', ['count' => $cotizacion->inspeccionAlmacen->count()]);

            // Debug: Verificar datos de proveedores
            if ($cotizacion->proveedores->count() > 0) {
                $firstProvider = $cotizacion->proveedores->first();
                Log::info('Primer proveedor:', [
                    'id' => $firstProvider->id,
                    'code_supplier' => $firstProvider->code_supplier,
                    'volumen_doc' => $firstProvider->volumen_doc,
                    'valor_doc' => $firstProvider->valor_doc,
                    'id_cotizacion' => $firstProvider->id_cotizacion
                ]);
            }

            // Transformar los datos para mantener la estructura original
            $files = $cotizacion->documentacion->map(function ($file) {
                return [
                    'id' => $file->id,
                    'file_url' => $file->file_url,
                    'folder_name' => $file->name,
                    'id_proveedor' => $file->id_proveedor
                ];
            });

            $filesAlmacenDocumentacion = $cotizacion->documentacionAlmacen->map(function ($file) {
                return [
                    'id' => $file->id,
                    'file_url' => $file->file_path,
                    'folder_name' => $file->file_name,
                    'file_name' => $file->file_name,
                    'id_proveedor' => $file->id_proveedor,
                    'file_ext' => $file->file_ext
                ];
            });

            $providers = $cotizacion->proveedores->map(function ($provider) {
                return [
                    'code_supplier' => $provider->code_supplier,
                    'id' => $provider->id,
                    'volumen_doc' => $provider->volumen_doc ? (float) $provider->volumen_doc : null,
                    'valor_doc' => $provider->valor_doc ? (float) $provider->valor_doc : null,
                    'factura_comercial' => $provider->factura_comercial,
                    'excel_confirmacion' => $provider->excel_confirmacion,
                    'packing_list' => $provider->packing_list
                ];
            });

            $filesAlmacenInspection = $cotizacion->inspeccionAlmacen->map(function ($file) {
                return [
                    'id' => $file->id,
                    'file_url' => $file->file_path,
                    'file_name' => $file->file_name,
                    'id_proveedor' => $file->id_proveedor,
                    'file_ext' => $file->file_type
                ];
            });

            // Debug: Verificar datos transformados
            Log::info('Files transformados:', ['count' => $files->count()]);
            Log::info('Providers transformados:', ['count' => $providers->count()]);
            if ($providers->count() > 0) {
                Log::info('Primer provider transformado:', $providers->first());
            }

            // Construir la respuesta similar a la original
            $result = [
                'id' => $cotizacion->id,
                'id_contenedor' => $cotizacion->id_contenedor,
                'id_tipo_cliente' => $cotizacion->id_tipo_cliente,
                'id_cliente' => $cotizacion->id_cliente,
                'fecha' => $cotizacion->fecha,
                'nombre' => $cotizacion->nombre,
                'documento' => $cotizacion->documento,
                'correo' => $cotizacion->correo,
                'telefono' => $cotizacion->telefono,
                'volumen' => $cotizacion->volumen,
                'cotizacion_file_url' => $cotizacion->cotizacion_file_url,
                'cotizacion_final_file_url' => $cotizacion->cotizacion_final_file_url,
                'estado' => $cotizacion->estado,
                'volumen_doc' => $cotizacion->volumen_doc,
                'valor_doc' => $cotizacion->valor_doc,
                'valor_cot' => $cotizacion->valor_cot,
                'volumen_china' => $cotizacion->volumen_china,
                'factura_comercial' => $cotizacion->factura_comercial,
                'id_usuario' => $cotizacion->id_usuario,
                'monto' => $cotizacion->monto,
                'fob' => $cotizacion->fob,
                'impuestos' => $cotizacion->impuestos,
                'tarifa' => $cotizacion->tarifa,
                'excel_comercial' => $cotizacion->excel_comercial,
                'excel_confirmacion' => $cotizacion->excel_confirmacion,
                'vol_selected' => $cotizacion->vol_selected,
                'estado_cliente' => $cotizacion->estado_cliente,
                'peso' => $cotizacion->peso,
                'tarifa_final' => $cotizacion->tarifa_final,
                'monto_final' => $cotizacion->monto_final,
                'volumen_final' => $cotizacion->volumen_final,
                'guia_remision_url' => $cotizacion->guia_remision_url,
                'factura_general_url' => $cotizacion->factura_general_url,
                'cotizacion_final_url' => $cotizacion->cotizacion_final_url,
                'estado_cotizador' => $cotizacion->estado_cotizador,
                'fecha_confirmacion' => $cotizacion->fecha_confirmacion,
                'estado_pagos_coordinacion' => $cotizacion->estado_pagos_coordinacion,
                'estado_cotizacion_final' => $cotizacion->estado_cotizacion_final,
                'impuestos_final' => $cotizacion->impuestos_final,
                'fob_final' => $cotizacion->fob_final,
                'note_administracion' => $cotizacion->note_administracion,
                'status_cliente_doc' => $cotizacion->status_cliente_doc,
                'logistica_final' => $cotizacion->logistica_final,
                'qty_item' => $cotizacion->qty_item,
                'id_cliente_importacion' => $cotizacion->id_cliente_importacion,
                'files' => $files,
                'files_almacen_documentacion' => $filesAlmacenDocumentacion,
                'providers' => $providers,
                'files_almacen_inspection' => $filesAlmacenInspection
            ];

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Documentación de clientes obtenida exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener documentación de clientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener documentación de clientes: ' . $e->getMessage()
            ], 500);
        }
    }
} 