<?php

namespace App\Http\Controllers\BaseDatos\Regulaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\BaseDatos\ProductoRegulacionAntidumping;
use App\Models\BaseDatos\ProductoRegulacionAntidumpingMedia;
use App\Models\BaseDatos\Regulaciones\ProductoRubro;
class AntidumpingController extends Controller
{
    /**
     * Obtener lista de rubros con regulaciones antidumping agrupadas
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);

            $query = ProductoRubro::with(['regulacionesAntidumping.media'])->where('tipo', ProductoRubro::TIPO_ANTIDUMPING);

            // Aplicar filtros si están presentes
            if ($request->has('search') && $request->search) {
                $query->where('nombre', 'like', '%' . $request->search . '%')
                    ->orWhere('descripcion', 'like', '%' . $request->search . '%');
            }

            $rubros = $query->paginate($perPage, ['*'], 'page', $page);

            // Transformar los datos para incluir regulaciones agrupadas
            $data = $rubros->items();
            foreach ($data as &$rubro) {
                $regulaciones = [];

                // Agregar regulaciones antidumping
                foreach ($rubro->regulacionesAntidumping as $antidumping) {
                    $regulaciones[] = [
                        'id' => $antidumping->id,
                        'descripcion' => $antidumping->descripcion_producto,
                        'partida' => $antidumping->partida,
                        'precio_declarado' => $antidumping->precio_declarado,
                        'antidumping' => $antidumping->antidumping,
                        'observaciones' => $antidumping->observaciones,
                        'imagenes' => $antidumping->media->map(function ($media) {
                            return $this->generateImageUrl($media->ruta);
                        })->toArray(),
                        'estado' => 'active',
                        'created_at' => $antidumping->created_at,
                        'updated_at' => $antidumping->updated_at
                    ];
                }

                // Ordenar regulaciones por fecha de creación (más recientes primero)
                usort($regulaciones, function ($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });

                $rubro->regulaciones = $regulaciones;

                // Remover la relación individual para limpiar la respuesta
                unset($rubro->regulacionesAntidumping);
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
            Log::error('Error al obtener regulaciones antidumping: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener regulaciones antidumping: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva regulación antidumping o actualizar existente
     */
    public function store(Request $request)
    {
        try {
       
            $isUpdate = $request->has('id_regulacion') && $request->id_regulacion;
            $antidumping = null;
            
            if ($isUpdate) {
                $antidumping = ProductoRegulacionAntidumping::find($request->id_regulacion);
                if (!$antidumping) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Regulación antidumping no encontrada'
                    ], 404);
                }
            }
            
            // Preparar datos para validación
            $data = $request->all();
            
            // Convertir campos específicos para validación
            if (isset($data['producto_id'])) {
                $data['id_rubro'] = (int) $data['producto_id']; // Mapear producto_id a id_rubro
            }
            
            if (isset($data['id_rubro'])) {
                $data['id_rubro'] = (int) $data['id_rubro'];
            }
            
           
            
            // Validar datos de entrada
            $validator = Validator::make($data, [
                'id_rubro' => 'required|integer|exists:bd_productos,id',
                'descripcion' => 'required|string|max:500',
                'partida' => 'required|string|max:50',
                'observaciones' => 'nullable|string|max:1000',
                'imagenes.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,avif' // 2MB máximo
            ], [
                'id_rubro.required' => 'El ID del rubro es obligatorio',
                'id_rubro.integer' => 'El ID del rubro debe ser un número entero',
                'id_rubro.exists' => 'El rubro seleccionado no existe',
                'descripcion.required' => 'La descripción es obligatoria',
                'descripcion.max' => 'La descripción no puede tener más de 500 caracteres',
                'partida.required' => 'La partida arancelaria es obligatoria',
                'partida.max' => 'La partida no puede tener más de 50 caracteres',
               
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
                Log::info('Iniciando actualización de regulación antidumping', ['id' => $antidumping->id]);
                
                // Preparar datos para actualizar
                $updateData = [
                    'id_rubro' => $data['id_rubro'],
                    'descripcion_producto' => $data['descripcion'],
                    'partida' => $data['partida'],
                    'precio_declarado' => $data['precio_declarado'],
                    'antidumping' => $data['antidumping'],
                    'observaciones' => $data['observaciones'] ?? null,
                ];
                
                Log::info('Datos a actualizar:', $updateData);
                
                // Actualizar campos
                $antidumping->update($updateData);
                
                // ===== MANEJO DE IMÁGENES =====
                
                // 1. ELIMINAR IMÁGENES ESPECIFICADAS
                if ($request->has('imagenes_eliminar') && is_array($request->imagenes_eliminar)) {
                    Log::info('Eliminando imágenes especificadas:', $request->imagenes_eliminar);
                    
                    foreach ($request->imagenes_eliminar as $imageId) {
                        $media = ProductoRegulacionAntidumpingMedia::where('id', $imageId)
                            ->where('id_regulacion', $antidumping->id)
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
                            $path = $imagen->storeAs('regulaciones/antidumping', $filename, 'public');
                            
                            ProductoRegulacionAntidumpingMedia::create([
                                'id_regulacion' => $antidumping->id,
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
                    $existingMedia = ProductoRegulacionAntidumpingMedia::where('id_regulacion', $antidumping->id)->get();
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
                                $path = $imagen->storeAs('regulaciones/antidumping', $filename, 'public');
                                
                                ProductoRegulacionAntidumpingMedia::create([
                                    'id_regulacion' => $antidumping->id,
                                    'extension' => $imagen->getClientOriginalExtension(),
                                    'peso' => $imagen->getSize(),
                                    'nombre_original' => $imagen->getClientOriginalName(),
                                    'ruta' => $path,
                                ]);
                            }
                        }
                    }
                }
                
                $antidumping->load(['rubro', 'media']);
                
                Log::info('Regulación antidumping actualizada exitosamente', ['id' => $antidumping->id]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Regulación antidumping actualizada exitosamente',
                    'data' => $antidumping
                ]);
                
            } else {
                // MODO CREACIÓN
                Log::info('Iniciando creación de nueva regulación antidumping');
                
                // Crear la regulación antidumping
                $antidumping = ProductoRegulacionAntidumping::create([
                    'id_rubro' => $data['id_rubro'],
                    'descripcion_producto' => $data['descripcion'],
                    'partida' => $data['partida'],
                    'precio_declarado' => $data['precio_declarado'],
                    'antidumping' => $data['antidumping'],
                    'observaciones' => $data['observaciones'] ?? null,
                ]);

                // Procesar imágenes si existen
                if ($request->hasFile('imagenes')) {
                    foreach ($request->file('imagenes') as $imagen) {
                        if ($imagen->isValid()) {
                            // Generar nombre único para el archivo
                            $filename = time() . '_' . uniqid() . '.' . $imagen->getClientOriginalExtension();
                            
                            // Guardar archivo en storage
                            $path = $imagen->storeAs('regulaciones/antidumping', $filename, 'public');
                            
                            // Crear registro en la tabla de media
                            ProductoRegulacionAntidumpingMedia::create([
                                'id_regulacion' => $antidumping->id,
                                'extension' => $imagen->getClientOriginalExtension(),
                                'peso' => $imagen->getSize(),
                                'nombre_original' => $imagen->getClientOriginalName(),
                                'ruta' => $path,
                            ]);
                        }
                    }
                }

                // Cargar relaciones para la respuesta
                $antidumping->load(['rubro', 'media']);

                Log::info('Regulación antidumping creada exitosamente', ['id' => $antidumping->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Regulación antidumping creada exitosamente',
                    'data' => $antidumping
                ], 201);
            }

        } catch (\Exception $e) {
            Log::error('Error al procesar regulación antidumping: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar regulación antidumping: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar URL completa para una imagen
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
        
        // Generar URL completa desde storage
        return Storage::disk('public')->url($ruta);
    }

    /**
     * Mostrar regulación antidumping específica
     */
    public function show($id)
    {
        try {
            $antidumping = ProductoRegulacionAntidumping::with(['rubro', 'media'])->find($id);
            
            if (!$antidumping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Regulación antidumping no encontrada'
                ], 404);
            }
            
            // Generar URLs completas para las imágenes
            $media = $antidumping->media->map(function ($media) {
                return [
                    'id' => $media->id,
                    'extension' => $media->extension,
                    'peso' => $media->peso,
                    'nombre_original' => $media->nombre_original,
                    'ruta' => $this->generateImageUrl($media->ruta),
                    'url' => $this->generateImageUrl($media->ruta),
                    'created_at' => $media->created_at,
                    'updated_at' => $media->updated_at
                ];
            });
            
            // Reemplazar la relación media con los datos procesados
            $antidumping->setRelation('media', $media);
           
            return response()->json([
                'success' => true,
                'data' => $antidumping
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener regulación antidumping: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener regulación antidumping: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar regulación antidumping
     */
    public function destroy($id)
    {
        try {
            $antidumping = ProductoRegulacionAntidumping::with('media')->find($id);

            if (!$antidumping) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Regulación antidumping no encontrada'
                ], 404);
            }

            // Eliminar archivos físicos
            foreach ($antidumping->media as $media) {
                if (Storage::disk('public')->exists($media->ruta)) {
                    Storage::disk('public')->delete($media->ruta);
                }
            }

            // Eliminar registros de media
            $antidumping->media()->delete();

            // Eliminar la regulación
            $antidumping->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Regulación antidumping eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar regulación antidumping: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar regulación antidumping: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Método de prueba para validar FormData
     */
    public function testValidation(Request $request)
    {
        try {
            Log::info('=== PRUEBA DE VALIDACIÓN FORMDATA ===');
            Log::info('Headers:', $request->headers->all());
            Log::info('Content-Type:', $request->header('Content-Type'));
            Log::info('Datos recibidos:', $request->all());
            Log::info('Archivos recibidos:', $request->allFiles());

            // Verificar si es FormData
            $isFormData = strpos($request->header('Content-Type'), 'multipart/form-data') !== false;
            Log::info('¿Es FormData?', ['isFormData' => $isFormData]);

            // Preparar datos para validación
            $data = $request->all();

            // Convertir campos específicos para validación
            if (isset($data['producto_id'])) {
                $originalValue = $data['producto_id'];
                $data['producto_id'] = (int) $data['producto_id'];
                Log::info('producto_id convertido:', [
                    'original' => $originalValue,
                    'tipo_original' => gettype($originalValue),
                    'convertido' => $data['producto_id'],
                    'tipo_convertido' => gettype($data['producto_id'])
                ]);
            }

            if (isset($data['precio_declarado'])) {
                $originalValue = $data['precio_declarado'];
                $data['precio_declarado'] = (float) $data['precio_declarado'];
                Log::info('precio_declarado convertido:', [
                    'original' => $originalValue,
                    'tipo_original' => gettype($originalValue),
                    'convertido' => $data['precio_declarado'],
                    'tipo_convertido' => gettype($data['precio_declarado'])
                ]);
            }

            if (isset($data['antidumping'])) {
                $originalValue = $data['antidumping'];
                $data['antidumping'] = filter_var($data['antidumping'], FILTER_VALIDATE_BOOLEAN);
                Log::info('antidumping convertido:', [
                    'original' => $originalValue,
                    'tipo_original' => gettype($originalValue),
                    'convertido' => $data['antidumping'],
                    'tipo_convertido' => gettype($data['antidumping'])
                ]);
            }

            // Validar datos
            $validator = Validator::make($data, [
                'producto_id' => 'required|integer|exists:bd_productos,id',
                'descripcion' => 'required|string|max:500',
                'partida' => 'required|string|max:50',
                'precio_declarado' => 'required|numeric|min:0',
                'antidumping' => 'required|boolean',
                'observaciones' => 'nullable|string|max:1000',
                'imagenes.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            if ($validator->fails()) {
                Log::warning('Validación fallida:', $validator->errors()->toArray());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validación fallida',
                    'errors' => $validator->errors(),
                    'debug' => [
                        'isFormData' => $isFormData,
                        'originalData' => $request->all(),
                        'processedData' => $data
                    ]
                ], 422);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Validación exitosa',
                'data' => $data,
                'debug' => [
                    'isFormData' => $isFormData,
                    'originalData' => $request->all(),
                    'processedData' => $data
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en prueba de validación: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error en prueba: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar imagen específica
     */
    public function deleteImage($id, $imageId)
    {
        try {
            $media = ProductoRegulacionAntidumpingMedia::where('id_regulacion', $id)
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
