<?php

namespace App\Http\Controllers\BaseDatos\Regulaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\BaseDatos\ProductoRegulacionEtiquetado;
use App\Models\BaseDatos\ProductoRegulacionEtiquetadoMedia;
use App\Models\BaseDatos\EntidadReguladora;
use App\Models\BaseDatos\Regulaciones\ProductoRubro;

class EtiquetadoController extends Controller
{
    /**
     * Obtener lista de rubros con sus regulaciones de etiquetado
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('limit', 50);
            $page = $request->get('page', 1);
            
            // Query para rubros con sus regulaciones de etiquetado
            $query = ProductoRubro::with([
                'regulacionesEtiquetado.media'
            ])->where('tipo', ProductoRubro::TIPO_ETIQUETADO);
            
            // Aplicar filtros si están presentes
            if ($request->has('search') && $request->search) {
                $query->where('nombre', 'like', '%' . $request->search . '%')
                      ->orWhere('descripcion', 'like', '%' . $request->search . '%');
            }
            
            $rubros = $query->paginate($perPage, ['*'], 'page', $page);
            $data = $rubros->items();
            
            // Transformar datos para agrupar regulaciones de etiquetado bajo rubros
            foreach ($data as &$rubro) {
                $regulaciones = [];
                
                // Agregar regulaciones de etiquetado
                foreach ($rubro->regulacionesEtiquetado as $etiquetado) {
                    $imagenes = [];
                    foreach ($etiquetado->media as $media) {
                        $imagenes[] = $this->generateImageUrl($media->ruta);
                    }
                    
                    $regulaciones[] = [
                        'id' => $etiquetado->id,
                        'tipo' => 'etiquetado',
                        'observaciones' => $etiquetado->observaciones,
                        'imagenes' => $imagenes,
                        'estado' => 'active',
                        'created_at' => $etiquetado->created_at,
                        'updated_at' => $etiquetado->updated_at
                    ];
                }
                
                // Ordenar regulaciones por fecha de creación
                usort($regulaciones, function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
                
                $rubro->regulaciones = $regulaciones;
                
                // Limpiar relaciones individuales para no duplicar datos
                unset($rubro->regulacionesEtiquetado);
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
            Log::error('Error al obtener rubros con regulaciones de etiquetado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener rubros con regulaciones de etiquetado: ' . $e->getMessage()
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
        
        // Limpiar la ruta de barras iniciales para evitar doble slash
        $ruta = ltrim($ruta, '/');
        
        // Construir URL manualmente para evitar problemas con Storage::url()
        $baseUrl = config('app.url');
        $storagePath = '/storage';
        
        // Asegurar que no haya doble slash
        $baseUrl = rtrim($baseUrl, '/');
        $storagePath = ltrim($storagePath, '/');
        $ruta = ltrim($ruta, '/');
        return $baseUrl . '/' . $storagePath . '/' . $ruta;
    }
    /**
     * Crear nueva regulación de etiquetado o actualizar existente
     */
    public function store(Request $request)
    {
        try {
            // Debug: Ver qué datos llegan
            Log::info('Datos recibidos en store etiquetado:', $request->all());

            // Verificar si es una actualización (si viene un ID)
            $isUpdate = $request->has('id_regulacion') && $request->id_regulacion;
            $etiquetado = null;

            if ($isUpdate) {
                $etiquetado = ProductoRegulacionEtiquetado::find($request->id_regulacion);
                if (!$etiquetado) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Regulación de etiquetado no encontrada'
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
                'imagenes.*' => 'nullable|image|mimes:jpeg,png,jpg,gif' // 2MB máximo
            ], [
                'id_rubro.required' => 'El ID del rubro es obligatorio',
                'id_rubro.integer' => 'El ID del rubro debe ser un número entero',
                'id_rubro.exists' => 'El rubro seleccionado no existe',
                'observaciones.max' => 'Las observaciones no pueden tener más de 1000 caracteres',
                'imagenes.*.image' => 'Los archivos deben ser imágenes',
                'imagenes.*.mimes' => 'Solo se permiten archivos JPEG, PNG, JPG o GIF',
                'imagenes.*.max' => 'Cada imagen no puede superar los 2MB'
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
                Log::info('Iniciando actualización de regulación de etiquetado', ['id' => $etiquetado->id]);

                // Preparar datos para actualizar
                $updateData = [
                    'id_rubro' => $data['id_rubro'],
                    'observaciones' => $data['observaciones'] ?? null,
                ];

                Log::info('Datos a actualizar:', $updateData);

                // Actualizar campos
                $etiquetado->update($updateData);

                // ===== MANEJO DE IMÁGENES =====

                // 1. ELIMINAR IMÁGENES ESPECIFICADAS
                if ($request->has('imagenes_eliminar') && is_array($request->imagenes_eliminar)) {
                    Log::info('Eliminando imágenes especificadas:', $request->imagenes_eliminar);

                    foreach ($request->imagenes_eliminar as $imageId) {
                        $media = ProductoRegulacionEtiquetadoMedia::where('id', $imageId)
                            ->where('id_regulacion', $etiquetado->id)
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
                            Log::warning('No se encontró la imagen para eliminar:', ['imageId' => $imageId]);
                        }
                    }
                }

                // 2. AGREGAR NUEVAS IMÁGENES
                if ($request->hasFile('imagenes')) {
                    Log::info('Procesando nuevas imágenes:', ['cantidad' => count($request->file('imagenes'))]);

                    foreach ($request->file('imagenes') as $imagen) {
                        if ($imagen->isValid()) {
                            $filename = time() . '_' . uniqid() . '.' . $imagen->getClientOriginalExtension();
                            $path = $imagen->storeAs('regulaciones/etiquetado', $filename, 'public');

                            ProductoRegulacionEtiquetadoMedia::create([
                                'id_regulacion' => $etiquetado->id,
                                'extension' => $imagen->getClientOriginalExtension(),
                                'peso' => $imagen->getSize(),
                                'nombre_original' => $imagen->getClientOriginalName(),
                                'ruta' => $path,
                            ]);

                            Log::info('Nueva imagen agregada:', [
                                'filename' => $filename,
                                'original_name' => $imagen->getClientOriginalName(),
                                'size' => $imagen->getSize()
                            ]);
                        }
                    }
                }

                // 3. REEMPLAZAR TODAS LAS IMÁGENES (si se especifica)
                if ($request->has('reemplazar_imagenes') && $request->reemplazar_imagenes === 'true') {
                    Log::info('Reemplazando todas las imágenes existentes');

                    // Eliminar todas las imágenes existentes
                    $existingMedia = ProductoRegulacionEtiquetadoMedia::where('id_regulacion', $etiquetado->id)->get();
                    foreach ($existingMedia as $media) {
                        if (Storage::disk('public')->exists($media->ruta)) {
                            Storage::disk('public')->delete($media->ruta);
                        }
                        $media->delete();
                    }

                    Log::info('Imágenes existentes eliminadas:', ['cantidad' => $existingMedia->count()]);

                    // Agregar las nuevas imágenes
                    if ($request->hasFile('imagenes')) {
                        foreach ($request->file('imagenes') as $imagen) {
                            if ($imagen->isValid()) {
                                $filename = time() . '_' . uniqid() . '.' . $imagen->getClientOriginalExtension();
                                $path = $imagen->storeAs('regulaciones/etiquetado', $filename, 'public');

                                ProductoRegulacionEtiquetadoMedia::create([
                                    'id_regulacion' => $etiquetado->id,
                                    'extension' => $imagen->getClientOriginalExtension(),
                                    'peso' => $imagen->getSize(),
                                    'nombre_original' => $imagen->getClientOriginalName(),
                                    'ruta' => $path,
                                ]);
                            }
                        }
                    }
                                }
                
                $etiquetado->load(['rubro', 'media']);
                
                Log::info('Regulación de etiquetado actuali zada exitosamente', ['id' => $etiquetado->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Regulación de etiquetado actualizada exitosamente',
                    'data' => $etiquetado
                ]);
            } else {
                // MODO CREACIÓN
                Log::info('Iniciando creación de nueva regulación de etiquetado');

                // Crear la regulación de etiquetado
                $etiquetado = ProductoRegulacionEtiquetado::create([
                    'id_rubro' => $data['id_rubro'],
                    'observaciones' => $data['observaciones'] ?? null,
                ]);

                // Procesar imágenes si existen
                if ($request->hasFile('imagenes')) {
                    foreach ($request->file('imagenes') as $imagen) {
                        if ($imagen->isValid()) {
                            // Generar nombre único para el archivo
                            $filename = time() . '_' . uniqid() . '.' . $imagen->getClientOriginalExtension();

                            // Guardar archivo en storage
                            $path = $imagen->storeAs('regulaciones/etiquetado', $filename, 'public');

                            // Crear registro en la tabla de media
                            ProductoRegulacionEtiquetadoMedia::create([
                                'id_regulacion' => $etiquetado->id,
                                
                                'extension' => $imagen->getClientOriginalExtension(),
                                'peso' => $imagen->getSize(),
                                'nombre_original' => $imagen->getClientOriginalName(),
                                'ruta' => $path,
                            ]);
                        }
                    }
                }

                // Cargar relaciones para la respuesta
                $etiquetado->load(['rubro', 'media']);

                Log::info('Regulación de etiquetado creada exitosamente', ['id' => $etiquetado->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Regulación de etiquetado creada exitosamente',
                    'data' => $etiquetado
                ], 201);
            }
        } catch (\Exception $e) {
            Log::error('Error al procesar regulación de etiquetado: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar regulación de etiquetado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener regulación de etiquetado específica
     */
    public function show($id)
    {
        try {
            $etiquetado = ProductoRegulacionEtiquetado::with(['rubro', 'media'])->find($id);

            if (!$etiquetado) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Regulación de etiquetado no encontrada'
                ], 404);
            }
            foreach ($etiquetado->media as $media) {
                $media->ruta = $this->generateImageUrl($media->ruta);
            }
            return response()->json([
                'success' => true,
                'data' => $etiquetado
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener regulación de etiquetado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener regulación de etiquetado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar regulación de etiquetado
     */
    public function update(Request $request, $id)
    {
        try {
            $etiquetado = ProductoRegulacionEtiquetado::find($id);

            if (!$etiquetado) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Regulación de etiquetado no encontrada'
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
                'imagenes.*' => 'nullable|image|mimes:jpeg,png,jpg,gif'
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
            if ($request->has('id_rubro')) {
                $updateData['id_rubro'] = $request->id_rubro;
            }
            if ($request->has('observaciones')) {
                $updateData['observaciones'] = $request->observaciones;
            }

            // Actualizar campos
            $etiquetado->update($updateData);

            // Procesar nuevas imágenes si existen
            if ($request->hasFile('imagenes')) {
                foreach ($request->file('imagenes') as $imagen) {
                    if ($imagen->isValid()) {
                        $filename = time() . '_' . uniqid() . '.' . $imagen->getClientOriginalExtension();
                        $path = $imagen->storeAs('regulaciones/etiquetado', $filename, 'public');

                        ProductoRegulacionEtiquetadoMedia::create([
                            'id_rubro' => $etiquetado->id_rubro,
                            'id_regulacion' => $etiquetado->id,
                            'extension' => $imagen->getClientOriginalExtension(),
                            'peso' => $imagen->getSize(),
                            'nombre_original' => $imagen->getClientOriginalName(),
                            'ruta' => $path,
                        ]);
                    }
                }
            }

            $etiquetado->load(['rubro', 'media']);

            return response()->json([
                'status' => 'success',
                'message' => 'Regulación de etiquetado actualizada exitosamente',
                'data' => $etiquetado
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar regulación de etiquetado: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar regulación de etiquetado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar regulación de etiquetado
     */
    public function destroy($id)
    {
        try {
            $etiquetado = ProductoRegulacionEtiquetado::with('media')->find($id);

            if (!$etiquetado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Regulación de etiquetado no encontrada'
                ], 404);
            }

            // Guardar el id_rubro antes de eliminar la regulación
            $idRubro = $etiquetado->id_rubro;

            // Eliminar archivos físicos
            foreach ($etiquetado->media as $media) {
                if (Storage::disk('public')->exists($media->ruta)) {
                    Storage::disk('public')->delete($media->ruta);
                }
            }

            // Eliminar registros de media
            $etiquetado->media()->delete();
            
            // Eliminar la regulación
            $etiquetado->delete();

            // Si el rubro ya no tiene regulaciones, eliminar el rubro
            if ($idRubro) {
                $regulacionesRestantes = ProductoRegulacionEtiquetado::where('id_rubro', $idRubro)->count();
                if ($regulacionesRestantes === 0) {
                    $rubro = ProductoRubro::find($idRubro);
                    if ($rubro) {
                        $rubro->delete();
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Regulación de etiquetado eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar regulación de etiquetado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar regulación de etiquetado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar imagen específica
     */
    public function deleteImage($id, $imageId)
    {
        try {
            $media = ProductoRegulacionEtiquetadoMedia::where('id_regulacion', $id)
                ->where('id', $imageId)
                ->first();

            if (!$media) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Imagen no encontrada'
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
                'message' => 'Imagen eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar imagen: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar imagen: ' . $e->getMessage()
            ], 500);
        }
    }
}
