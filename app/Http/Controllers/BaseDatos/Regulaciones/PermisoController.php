<?php

namespace App\Http\Controllers\BaseDatos\Regulaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BaseDatos\ProductoRegulacionPermiso;
use App\Models\BaseDatos\ProductoRegulacionPermisoMedia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\BaseDatos\EntidadReguladora;
use App\Models\BaseDatos\ProductoRubro;

class PermisoController extends Controller
{
    /**
     * Obtener lista de entidades reguladoras con sus regulaciones de permisos
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            // Query para entidades reguladoras con sus regulaciones de permisos
            $query = EntidadReguladora::with([
                'regulacionesPermiso.media',
                'regulacionesPermiso.rubro'
            ]);
            
            // Aplicar filtros si están presentes
            if ($request->has('search') && $request->search) {
                $query->where('nombre', 'like', '%' . $request->search . '%')
                      ->orWhere('descripcion', 'like', '%' . $request->search . '%');
            }
            
            $entidades = $query->paginate($perPage, ['*'], 'page', $page);
            $data = $entidades->items();
            
            // Transformar datos para agrupar regulaciones de permisos bajo entidades
            foreach ($data as &$entidad) {
                $regulaciones = [];
                
                // Agregar regulaciones de permisos
                foreach ($entidad->regulacionesPermiso as $permiso) {
                    $documentos = [];
                    foreach ($permiso->media as $media) {
                        $documentos[] = '/storage/' . $media->ruta;
                    }
                    
                    $regulaciones[] = [
                        'id' => $permiso->id,
                        'tipo' => 'permiso',
                        'rubro_nombre' => $permiso->rubro ? $permiso->rubro->nombre : null,
                        'nombre' => $permiso->nombre,
                        'c_permiso' => $permiso->c_permiso,
                        'c_tramitador' => $permiso->c_tramitador,
                        'observaciones' => $permiso->observaciones,
                        'documentos' => $documentos,
                        'estado' => 'active',
                        'created_at' => $permiso->created_at,
                        'updated_at' => $permiso->updated_at
                    ];
                }
                
                // Ordenar regulaciones por fecha de creación
                usort($regulaciones, function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
                
                $entidad->regulaciones = $regulaciones;
                
                // Limpiar relaciones individuales para no duplicar datos
                unset($entidad->regulacionesPermiso);
            }
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $entidades->currentPage(),
                    'last_page' => $entidades->lastPage(),
                    'per_page' => $entidades->perPage(),
                    'total' => $entidades->total(),
                    'from' => $entidades->firstItem(),
                    'to' => $entidades->lastItem(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener entidades con regulaciones de permisos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener entidades con regulaciones de permisos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva regulación de permiso o actualizar existente
     */
    public function store(Request $request)
    {
        try {
            // Debug: Ver qué datos llegan
            Log::info('Datos recibidos en store permisos:', $request->all());
            
            // Verificar si es una actualización (si viene un ID)
            $isUpdate = $request->has('id_regulacion') && $request->id_regulacion;
            $permiso = null;
            
            if ($isUpdate) {
                $permiso = ProductoRegulacionPermiso::find($request->id_regulacion);
                if (!$permiso) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Regulación de permiso no encontrada'
                    ], 404);
                }
                Log::info('Modo actualización detectado', ['id' => $request->id_regulacion]);
            }
            
            // Preparar datos para validación
            $data = $request->all();
            
            // Convertir campos específicos para validación
            if (isset($data['entidad_id'])) {
                $data['id_entidad_reguladora'] = (int) $data['entidad_id'];
            }
            
        
            
            if (isset($data['codigo_permiso'])) {
                $data['c_permiso'] = (float) $data['codigo_permiso'];
            }
            
            if (isset($data['costo_tramitador'])) {
                $data['c_tramitador'] = (float) $data['costo_tramitador'];
            }
            
            // Validar datos de entrada
            $validator = Validator::make($data, [
                'id_entidad_reguladora' => 'required|integer|exists:bd_entidades_reguladoras,id',
                'nombre_permiso' => 'required|string|max:255',
                'codigo_permiso' => 'nullable|numeric|min:0',
                'costo_tramitador' => 'nullable|numeric|min:0',
                'observaciones' => 'nullable|string|max:1000',
                'documentos.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:5120' // 5MB máximo
            ], [

                'id_entidad_reguladora.required' => 'La entidad reguladora es obligatoria',
                'id_entidad_reguladora.integer' => 'La entidad reguladora debe ser un número entero',
                'id_entidad_reguladora.exists' => 'La entidad reguladora seleccionada no existe',
                'nombre_permiso.required' => 'El nombre del permiso es obligatorio',
                'nombre_permiso.max' => 'El nombre del permiso no puede tener más de 255 caracteres',
                'codigo_permiso.numeric' => 'El código del permiso debe ser un número',
                'codigo_permiso.min' => 'El código del permiso debe ser mayor o igual a 0',
                'costo_tramitador.numeric' => 'El costo del tramitador debe ser un número',
                'costo_tramitador.min' => 'El costo del tramitador debe ser mayor o igual a 0',
                'observaciones.max' => 'Las observaciones no pueden tener más de 1000 caracteres',
                'documentos.*.file' => 'Los archivos deben ser documentos válidos',
                'documentos.*.mimes' => 'Solo se permiten archivos PDF, DOC, DOCX, XLS o XLSX',
                'documentos.*.max' => 'Cada documento no puede superar los 5MB'
            ]);

            if ($validator->fails()) {
                Log::warning('Validación fallida:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($isUpdate) {
                // MODO ACTUALIZACIÓN
                Log::info('Iniciando actualización de regulación de permiso', ['id' => $permiso->id]);
                
                // Preparar datos para actualizar
                $updateData = [
                    'id_entidad_reguladora' => $data['id_entidad_reguladora'],
                    'nombre' => $data['nombre_permiso'],
                    'c_permiso' => $data['codigo_permiso'] ?? null,
                    'c_tramitador' => $data['costo_tramitador'] ?? null,
                    'observaciones' => $data['observaciones'] ?? null,
                ];
                
                Log::info('Datos a actualizar:', $updateData);
                
                // Actualizar campos
                $permiso->update($updateData);
                
                // ===== MANEJO DE DOCUMENTOS =====
                
                // 1. ELIMINAR DOCUMENTOS ESPECIFICADOS
                if ($request->has('documentos_eliminar') && is_array($request->documentos_eliminar)) {
                    Log::info('Eliminando documentos especificados:', $request->documentos_eliminar);
                    
                    foreach ($request->documentos_eliminar as $documentId) {
                        $media = ProductoRegulacionPermisoMedia::where('id', $documentId)
                            ->where('id_regulacion', $permiso->id)
                            ->first();
                            
                        if ($media) {
                            // Eliminar archivo físico
                            if (Storage::disk('public')->exists($media->ruta)) {
                                Storage::disk('public')->delete($media->ruta);
                                Log::info('Archivo eliminado del storage:', ['ruta' => $media->ruta]);
                            }
                            
                            // Eliminar registro de la base de datos
                            $media->delete();
                            Log::info('Registro de media eliminado:', ['id' => $media->id]);
                        } else {
                            Log::warning('No se encontró el documento para eliminar:', ['documentId' => $documentId]);
                        }
                    }
                }
                
                // 2. AGREGAR NUEVOS DOCUMENTOS
                if ($request->hasFile('documentos')) {
                    Log::info('Procesando nuevos documentos:', ['cantidad' => count($request->file('documentos'))]);
                    
                    foreach ($request->file('documentos') as $documento) {
                        if ($documento->isValid()) {
                            $filename = time() . '_' . uniqid() . '.' . $documento->getClientOriginalExtension();
                            $path = $documento->storeAs('regulaciones/permisos', $filename, 'public');
                            
                            ProductoRegulacionPermisoMedia::create([
                                'id_regulacion' => $permiso->id,
                                'extension' => $documento->getClientOriginalExtension(),
                                'peso' => $documento->getSize(),
                                'nombre_original' => $documento->getClientOriginalName(),
                                'ruta' => $path,
                            ]);
                            
                            Log::info('Nuevo documento agregado:', [
                                'filename' => $filename,
                                'original_name' => $documento->getClientOriginalName(),
                                'size' => $documento->getSize()
                            ]);
                        }
                    }
                }
                
                // 3. REEMPLAZAR TODOS LOS DOCUMENTOS (si se especifica)
                if ($request->has('reemplazar_documentos') && $request->reemplazar_documentos === 'true') {
                    Log::info('Reemplazando todos los documentos existentes');
                    
                    // Eliminar todos los documentos existentes
                    $existingMedia = ProductoRegulacionPermisoMedia::where('id_regulacion', $permiso->id)->get();
                    foreach ($existingMedia as $media) {
                        if (Storage::disk('public')->exists($media->ruta)) {
                            Storage::disk('public')->delete($media->ruta);
                        }
                        $media->delete();
                    }
                    
                    Log::info('Documentos existentes eliminados:', ['cantidad' => $existingMedia->count()]);
                    
                    // Agregar los nuevos documentos
                    if ($request->hasFile('documentos')) {
                        foreach ($request->file('documentos') as $documento) {
                            if ($documento->isValid()) {
                                $filename = time() . '_' . uniqid() . '.' . $documento->getClientOriginalExtension();
                                $path = $documento->storeAs('regulaciones/permisos', $filename, 'public');
                                
                                ProductoRegulacionPermisoMedia::create([
                                    'id_regulacion' => $permiso->id,
                                    'extension' => $documento->getClientOriginalExtension(),
                                    'peso' => $documento->getSize(),
                                    'nombre_original' => $documento->getClientOriginalName(),
                                    'ruta' => $path,
                                ]);
                            }
                        }
                    }
                }
                
                $permiso->load(['rubro', 'entidadReguladora', 'media']);
                
                Log::info('Regulación de permiso actualizada exitosamente', ['id' => $permiso->id]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Regulación de permiso actualizada exitosamente',
                    'data' => $permiso
                ]);
                
            } else {
                // MODO CREACIÓN
                Log::info('Iniciando creación de nueva regulación de permiso');
                
                // Crear la regulación de permiso
                $permiso = ProductoRegulacionPermiso::create([
                    'id_entidad_reguladora' => $data['id_entidad_reguladora'],
                    'nombre' => $data['nombre_permiso'],
                    'c_permiso' => $data['codigo_permiso'] ?? null,
                    'c_tramitador' => $data['costo_tramitador'] ?? null,
                    'observaciones' => $data['observaciones'] ?? null,
                ]);

                // Procesar documentos si existen
                if ($request->hasFile('documentos')) {
                    foreach ($request->file('documentos') as $documento) {
                        if ($documento->isValid()) {
                            // Generar nombre único para el archivo
                            $filename = time() . '_' . uniqid() . '.' . $documento->getClientOriginalExtension();
                            
                            // Guardar archivo en storage
                            $path = $documento->storeAs('regulaciones/permisos', $filename, 'public');
                            
                            // Crear registro en la tabla de media
                            ProductoRegulacionPermisoMedia::create([
                                'id_regulacion' => $permiso->id,
                                'extension' => $documento->getClientOriginalExtension(),
                                'peso' => $documento->getSize(),
                                'nombre_original' => $documento->getClientOriginalName(),
                                'ruta' => $path,
                            ]);
                        }
                    }
                }

                // Cargar relaciones para la respuesta
                $permiso->load(['rubro', 'entidadReguladora', 'media']);

                Log::info('Regulación de permiso creada exitosamente', ['id' => $permiso->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Regulación de permiso creada exitosamente',
                    'data' => $permiso
                ], 201);
            }

        } catch (\Exception $e) {
            Log::error('Error al procesar regulación de permiso: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar regulación de permiso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener regulación de permiso específica
     */
    public function show($id)
    {
        try {
            $permiso = ProductoRegulacionPermiso::with(['rubro', 'entidadReguladora', 'media'])->find($id);
            
            if (!$permiso) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Regulación de permiso no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $permiso
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener regulación de permiso: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener regulación de permiso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar regulación de permiso
     */
    public function update(Request $request, $id)
    {
        try {
            $permiso = ProductoRegulacionPermiso::find($id);
            
            if (!$permiso) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Regulación de permiso no encontrada'
                ], 404);
            }

            // Preparar datos para validación
            $data = $request->all();
            
            // Convertir campos específicos
            if (isset($data['entidad_id'])) {
                $data['entidad_id'] = (int) $data['entidad_id'];
            }
            
            if (isset($data['costo_base'])) {
                $data['costo_base'] = (float) $data['costo_base'];
            }
            
            if (isset($data['costo_tramitador'])) {
                $data['costo_tramitador'] = (float) $data['costo_tramitador'];
            }

            // Validar datos de entrada
            $validator = Validator::make($data, [
                'entidad_id' => 'sometimes|required|integer|exists:bd_entidades_reguladoras,id',
                'nombre_permiso' => 'sometimes|required|string|max:255',
                'codigo_permiso' => 'sometimes|required|string|max:100',
                'costo_base' => 'sometimes|required|numeric|min:0',
                'costo_tramitador' => 'sometimes|required|numeric|min:0',
                'observaciones' => 'nullable|string|max:1000',
                'documentos.*' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Preparar datos para actualizar
            $updateData = [];
            if ($request->has('entidad_id')) {
                $updateData['id_entidad_reguladora'] = $request->entidad_id;
            }
            if ($request->has('nombre_permiso')) {
                $updateData['nombre'] = $request->nombre_permiso;
            }
            if ($request->has('codigo_permiso')) {
                $updateData['c_permiso'] = $request->codigo_permiso;
            }
            if ($request->has('costo_tramitador')) {
                $updateData['c_tramitador'] = $request->costo_tramitador;
            }
            if ($request->has('observaciones')) {
                $updateData['observaciones'] = $request->observaciones;
            }

            // Actualizar campos
            $permiso->update($updateData);

            // Procesar nuevos documentos si existen
            if ($request->hasFile('documentos')) {
                foreach ($request->file('documentos') as $documento) {
                    if ($documento->isValid()) {
                        $filename = time() . '_' . uniqid() . '.' . $documento->getClientOriginalExtension();
                        $path = $documento->storeAs('regulaciones/permisos', $filename, 'public');
                        
                        ProductoRegulacionPermisoMedia::create([
                            'id_regulacion' => $permiso->id,
                            'extension' => $documento->getClientOriginalExtension(),
                            'peso' => $documento->getSize(),
                            'nombre_original' => $documento->getClientOriginalName(),
                            'ruta' => $path,
                        ]);
                    }
                }
            }

            $permiso->load(['rubro', 'entidadReguladora', 'media']);

            return response()->json([
                'status' => 'success',
                'message' => 'Regulación de permiso actualizada exitosamente',
                'data' => $permiso
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar regulación de permiso: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar regulación de permiso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar regulación de permiso
     */
    public function destroy($id)
    {
        try {
            $permiso = ProductoRegulacionPermiso::with('media')->find($id);
            
            if (!$permiso) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Regulación de permiso no encontrada'
                ], 404);
            }

            // Eliminar archivos físicos
            foreach ($permiso->media as $media) {
                if (Storage::disk('public')->exists($media->ruta)) {
                    Storage::disk('public')->delete($media->ruta);
                }
            }

            // Eliminar registros de media
            $permiso->media()->delete();
            
            // Eliminar la regulación
            $permiso->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Regulación de permiso eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar regulación de permiso: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar regulación de permiso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar documento específico
     */
    public function deleteDocument($id, $documentId)
    {
        try {
            $media = ProductoRegulacionPermisoMedia::where('id_regulacion', $id)
                ->where('id', $documentId)
                ->first();
            
            if (!$media) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Documento no encontrado'
                ], 404);
            }

            // Eliminar archivo físico
            if (Storage::disk('public')->exists($media->ruta)) {
                Storage::disk('public')->delete($media->ruta);
            }

            // Eliminar registro
            $media->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Documento eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar documento: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar documento: ' . $e->getMessage()
            ], 500);
        }
    }
} 