<?php

namespace App\Http\Controllers\BaseDatos\Regulaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BaseDatos\ProductoRegulacionDocumentoEspecial;
use App\Models\BaseDatos\ProductoRegulacionDocumentoEspecialMedia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\BaseDatos\Regulaciones\ProductoRubro;

class DocumentosEspecialesController extends Controller
{
    /**
     * Obtener lista de rubros con sus regulaciones de documentos especiales
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            // Query para rubros con sus regulaciones de documentos especiales
            $query = ProductoRubro::with([
                'regulacionesDocumentosEspeciales.media'
            ])->where('tipo', ProductoRubro::TIPO_DOCUMENTO_ESPECIAL);
            
            // Aplicar filtros si están presentes
            if ($request->has('search') && $request->search) {
                $query->where('nombre', 'like', '%' . $request->search . '%')
                      ->orWhere('descripcion', 'like', '%' . $request->search . '%');
            }
            
            $rubros = $query->paginate($perPage, ['*'], 'page', $page);
            $data = $rubros->items();
            
            // Transformar datos para agrupar regulaciones de documentos especiales bajo rubros
            foreach ($data as &$rubro) {
                $regulaciones = [];
                
                // Agregar regulaciones de documentos especiales
                foreach ($rubro->regulacionesDocumentosEspeciales as $documento) {
                    $documentos = [];
                    foreach ($documento->media as $media) {
                        $documentos[] = $this->generateImageUrl($media->ruta);
                    }
                    
                    $regulaciones[] = [
                        'id' => $documento->id,
                        'tipo' => 'documento_especial',
                        'observaciones' => $documento->observaciones,
                        'documentos' => $documentos,
                        'estado' => 'active',
                        'created_at' => $documento->created_at,
                        'updated_at' => $documento->updated_at
                    ];
                }
                
                // Ordenar regulaciones por fecha de creación
                usort($regulaciones, function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
                
                $rubro->regulaciones = $regulaciones;
                
                // Limpiar relaciones individuales para no duplicar datos
                unset($rubro->regulacionesDocumentosEspeciales);
            }
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $rubros->currentPage(),
                    'last_page' => $rubros->lastPage(),
                    'per_page' => $rubros->perPage(),
                    'total' => $rubros->total(),
                    'from' => $rubros->firstItem(),
                    'to' => $rubros->lastItem(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener rubros con regulaciones de documentos especiales: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener rubros con regulaciones de documentos especiales: ' . $e->getMessage()
            ], 500);
        }
    }
    private function generateImageUrl($ruta)
    {
        if (empty($ruta)) {
            return null;
        }
        
        // Si ya es una URL completa, devolverla tal como está
        if (filter_var($ruta, FILTER_VALIDATE_URL)) {
            return $ruta;
        }
        
        // Generar URL completa desde storage
        return Storage::disk('public')->url($ruta);
    }
    /**
     * Crear nueva regulación de documentos especiales o actualizar existente
     */
    public function store(Request $request)
    {
        try {
            // Debug: Ver qué datos llegan
            Log::info('Datos recibidos en store documentos especiales:', $request->all());
            
            // Verificar si es una actualización (si viene un ID)
            $isUpdate = $request->has('id_regulacion') && $request->id_regulacion;
            $documento = null;
            
            if ($isUpdate) {
                $documento = ProductoRegulacionDocumentoEspecial::find($request->id_regulacion);
                if (!$documento) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Regulación de documentos especiales no encontrada'
                    ], 404);
                }
                Log::info('Modo actualización detectado', ['id' => $request->id_regulacion]);
            }
            
            // Preparar datos para validación
            $data = $request->all();
            
            // Convertir campos específicos para validación
            if (isset($data['id_rubro'])) {
                $data['id_rubro'] = (int) $data['id_rubro'];
            }
            
            // Validar datos de entrada
            $validator = Validator::make($data, [
                'id_rubro' => 'required|integer|exists:bd_productos,id',
                'observaciones' => 'nullable|string|max:1000',
                'documentos.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:5120' // 5MB máximo
            ], [
                'id_rubro.required' => 'El ID del rubro es obligatorio',
                'id_rubro.integer' => 'El ID del rubro debe ser un número entero',
                'id_rubro.exists' => 'El rubro seleccionado no existe',
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
                Log::info('Iniciando actualización de regulación de documentos especiales', ['id' => $documento->id]);
                
                // Preparar datos para actualizar
                $updateData = [
                    'id_rubro' => $data['id_rubro'],
                    'observaciones' => $data['observaciones'] ?? null,
                ];
                
                Log::info('Datos a actualizar:', $updateData);
                
                // Actualizar campos
                $documento->update($updateData);
                
                // ===== MANEJO DE DOCUMENTOS =====
                
                // 1. ELIMINAR DOCUMENTOS ESPECIFICADOS
                if ($request->has('documentos_eliminar') && is_array($request->documentos_eliminar)) {
                    Log::info('Eliminando documentos especificados:', $request->documentos_eliminar);
                    
                    foreach ($request->documentos_eliminar as $documentId) {
                        $media = ProductoRegulacionDocumentoEspecialMedia::where('id', $documentId)
                            ->where('id_regulacion', $documento->id)
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
                    
                    foreach ($request->file('documentos') as $documentoFile) {
                        if ($documentoFile->isValid()) {
                            $filename = time() . '_' . uniqid() . '.' . $documentoFile->getClientOriginalExtension();
                            $path = $documentoFile->storeAs('regulaciones/documentos-especiales', $filename, 'public');
                            
                            ProductoRegulacionDocumentoEspecialMedia::create([
                                'id_regulacion' => $documento->id,
                                'extension' => $documentoFile->getClientOriginalExtension(),
                                'peso' => $documentoFile->getSize(),
                                'nombre_original' => $documentoFile->getClientOriginalName(),
                                'ruta' => $path,
                            ]);
                            
                            Log::info('Nuevo documento agregado:', [
                                'filename' => $filename,
                                'original_name' => $documentoFile->getClientOriginalName(),
                                'size' => $documentoFile->getSize()
                            ]);
                        }
                    }
                }
                
                // 3. REEMPLAZAR TODOS LOS DOCUMENTOS (si se especifica)
                if ($request->has('reemplazar_documentos') && $request->reemplazar_documentos === 'true') {
                    Log::info('Reemplazando todos los documentos existentes');
                    
                    // Eliminar todos los documentos existentes
                    $existingMedia = ProductoRegulacionDocumentoEspecialMedia::where('id_regulacion', $documento->id)->get();
                    foreach ($existingMedia as $media) {
                        if (Storage::disk('public')->exists($media->ruta)) {
                            Storage::disk('public')->delete($media->ruta);
                        }
                        $media->delete();
                    }
                    
                    Log::info('Documentos existentes eliminados:', ['cantidad' => $existingMedia->count()]);
                    
                    // Agregar los nuevos documentos
                    if ($request->hasFile('documentos')) {
                        foreach ($request->file('documentos') as $documentoFile) {
                            if ($documentoFile->isValid()) {
                                $filename = time() . '_' . uniqid() . '.' . $documentoFile->getClientOriginalExtension();
                                $path = $documentoFile->storeAs('regulaciones/documentos-especiales', $filename, 'public');
                                
                                ProductoRegulacionDocumentoEspecialMedia::create([
                                    'id_regulacion' => $documento->id,
                                    'extension' => $documentoFile->getClientOriginalExtension(),
                                    'peso' => $documentoFile->getSize(),
                                    'nombre_original' => $documentoFile->getClientOriginalName(),
                                    'ruta' => $path,
                                ]);
                            }
                        }
                    }
                }
                
                $documento->load(['rubro', 'media']);
                
                Log::info('Regulación de documentos especiales actualizada exitosamente', ['id' => $documento->id]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Regulación de documentos especiales actualizada exitosamente',
                    'data' => $documento
                ]);
                
            } else {
                // MODO CREACIÓN
                Log::info('Iniciando creación de nueva regulación de documentos especiales');
                
                // Crear la regulación de documentos especiales
                $documento = ProductoRegulacionDocumentoEspecial::create([
                    'id_rubro' => $data['id_rubro'],
                    'observaciones' => $data['observaciones'] ?? null,
                ]);

                // Procesar documentos si existen
                if ($request->hasFile('documentos')) {
                    foreach ($request->file('documentos') as $documentoFile) {
                        if ($documentoFile->isValid()) {
                            // Generar nombre único para el archivo
                            $filename = time() . '_' . uniqid() . '.' . $documentoFile->getClientOriginalExtension();
                            
                            // Guardar archivo en storage
                            $path = $documentoFile->storeAs('regulaciones/documentos-especiales', $filename, 'public');
                            
                            // Crear registro en la tabla de media
                            ProductoRegulacionDocumentoEspecialMedia::create([
                                'id_regulacion' => $documento->id,
                                'extension' => $documentoFile->getClientOriginalExtension(),
                                'peso' => $documentoFile->getSize(),
                                'nombre_original' => $documentoFile->getClientOriginalName(),
                                'ruta' => $path,
                            ]);
                        }
                    }
                }

                // Cargar relaciones para la respuesta
                $documento->load(['rubro', 'media']);

                Log::info('Regulación de documentos especiales creada exitosamente', ['id' => $documento->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Regulación de documentos especiales creada exitosamente',
                    'data' => $documento
                ], 201);
            }

        } catch (\Exception $e) {
            Log::error('Error al procesar regulación de documentos especiales: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar regulación de documentos especiales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener regulación de documentos especiales específica
     */
    public function show($id)
    {
        try {
            $documento = ProductoRegulacionDocumentoEspecial::with(['rubro', 'media'])->find($id);
            
            if (!$documento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Regulación de documentos especiales no encontrada'
                ], 404);
            }
            foreach ($documento->media as $media) {
                $media->ruta = $this->generateImageUrl($media->ruta);
            }

            return response()->json([
                'success' => true,
                'data' => $documento
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener regulación de documentos especiales: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener regulación de documentos especiales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar regulación de documentos especiales
     */
    public function update(Request $request, $id)
    {
        try {
            $documento = ProductoRegulacionDocumentoEspecial::find($id);
            
            if (!$documento) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Regulación de documentos especiales no encontrada'
                ], 404);
            }

            // Preparar datos para validación
            $data = $request->all();
            
            // Convertir campos específicos
            if (isset($data['id_rubro'])) {
                $data['id_rubro'] = (int) $data['id_rubro'];
            }

            // Validar datos de entrada
            $validator = Validator::make($data, [
                'id_rubro' => 'sometimes|required|integer|exists:bd_productos,id',
                'observaciones' => 'nullable|string|max:1000',
                'documentos.*' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Preparar datos para actualizar
            $updateData = [];
            if ($request->has('id_rubro')) {
                $updateData['id_rubro'] = $request->id_rubro;
            }
            if ($request->has('observaciones')) {
                $updateData['observaciones'] = $request->observaciones;
            }

            // Actualizar campos
            $documento->update($updateData);

            // Procesar nuevos documentos si existen
            if ($request->hasFile('documentos')) {
                foreach ($request->file('documentos') as $archivo) {
                    if ($archivo->isValid()) {
                        $filename = time() . '_' . uniqid() . '.' . $archivo->getClientOriginalExtension();
                        $path = $archivo->storeAs('regulaciones/documentos-especiales', $filename, 'public');
                        
                        ProductoRegulacionDocumentoEspecialMedia::create([
                            'id_rubro' => $documento->id_rubro,
                            'id_regulacion' => $documento->id,
                            'extension' => $archivo->getClientOriginalExtension(),
                            'peso' => $archivo->getSize(),
                            'nombre_original' => $archivo->getClientOriginalName(),
                            'ruta' => $path,
                        ]);
                    }
                }
            }

            $documento->load(['rubro', 'media']);

            return response()->json([
                'success' => true,
                'message' => 'Regulación de documentos especiales actualizada exitosamente',
                'data' => $documento
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar regulación de documentos especiales: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar regulación de documentos especiales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar regulación de documentos especiales
     */
    public function destroy($id)
    {
        try {
            $documento = ProductoRegulacionDocumentoEspecial::with('media')->find($id);
            
            if (!$documento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Regulación de documentos especiales no encontrada'
                ], 404);
            }

            // Eliminar archivos físicos
            foreach ($documento->media as $media) {
                if (Storage::disk('public')->exists($media->ruta)) {
                    Storage::disk('public')->delete($media->ruta);
                }
            }

            // Eliminar registros de media
            $documento->media()->delete();
            
            // Eliminar la regulación
            $documento->delete();

            return response()->json([
                'success' => true,
                'message' => 'Regulación de documentos especiales eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar regulación de documentos especiales: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar regulación de documentos especiales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar documento específico
     */
    public function deleteDocument($id, $documentId)
    {
        try {
            $media = ProductoRegulacionDocumentoEspecialMedia::where('id_regulacion', $id)
                ->where('id', $documentId)
                ->first();
            
            if (!$media) {
                return response()->json([
                    'success' => false,
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
                'success' => true,
                'message' => 'Documento eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar documento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar documento: ' . $e->getMessage()
            ], 500);
        }
    }
} 