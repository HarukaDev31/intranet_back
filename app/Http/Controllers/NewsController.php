<?php

namespace App\Http\Controllers;

use App\Models\SystemNews;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NewsController extends Controller
{
    /**
     * Obtener lista de noticias (público - solo publicadas)
     */
    public function index(Request $request)
    {
        try {
            $query = SystemNews::published()
                ->orderByPublished('desc');

            // Filtros opcionales
            if ($request->has('type')) {
                $query->byType($request->type);
            }

            if ($request->has('solicitada_por')) {
                $query->bySolicitadaPor($request->solicitada_por);
            }

            // Paginación
            $perPage = $request->get('per_page', 10);
            $news = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $news->items(),
                'pagination' => [
                    'current_page' => $news->currentPage(),
                    'last_page' => $news->lastPage(),
                    'per_page' => $news->perPage(),
                    'total' => $news->total()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener noticias: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener noticias',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todas las noticias (admin - incluye no publicadas)
     */
    public function adminIndex(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            // Verificar permisos de administrador
            if ($user->getNombreGrupo() !== Usuario::ROL_ADMINISTRACION) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para acceder a esta función'
                ], 403);
            }

            $query = SystemNews::withTrashed()
                ->orderByPublished('desc');

            // Filtros
            if ($request->has('type')) {
                $query->byType($request->type);
            }

            if ($request->has('solicitada_por')) {
                $query->bySolicitadaPor($request->solicitada_por);
            }

            if ($request->has('is_published')) {
                $query->where('is_published', $request->is_published);
            }

            // Paginación
            $perPage = $request->get('per_page', 10);
            $news = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $news->items(),
                'pagination' => [
                    'current_page' => $news->currentPage(),
                    'last_page' => $news->lastPage(),
                    'per_page' => $news->perPage(),
                    'total' => $news->total()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener noticias (admin): ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener noticias',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una noticia específica
     */
    public function show($id)
    {
        try {
            $news = SystemNews::published()->find($id);

            if (!$news) {
                return response()->json([
                    'success' => false,
                    'message' => 'Noticia no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $news
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener noticia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener noticia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva noticia (admin)
     */
    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            // Verificar permisos de administrador
            if (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para crear noticias'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'summary' => 'nullable|string|max:500',
                'type' => 'required|in:update,feature,fix,announcement',
                'solicitada_por' => 'nullable|in:CEO,EQUIPO_DE_COORDINACION,EQUIPO_DE_VENTAS,EQUIPO_DE_CURSO,EQUIPO_DE_DOCUMENTACION,ADMINISTRACION,EQUIPO_DE_TI,EQUIPO_DE_MARKETING',
                'is_published' => 'boolean',
                'published_at' => 'nullable|date',
                'redirect' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $news = SystemNews::create([
                'title' => $request->title,
                'content' => $request->content,
                'summary' => $request->summary,
                'type' => $request->type,
                'solicitada_por' => $request->solicitada_por,
                'is_published' => $request->is_published ?? false,
                'published_at' => $request->published_at,
                'redirect' => $request->redirect,
                'created_by' => $user->getIdUsuario(),
                'created_by_name' => $user->No_Nombres_Apellidos ?? $user->No_Usuario
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Noticia creada exitosamente',
                'data' => $news
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear noticia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear noticia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una noticia (admin)
     */
    public function update(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            // Verificar permisos de administrador
            if (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para actualizar noticias'
                ], 403);
            }

            $news = SystemNews::find($id);

            if (!$news) {
                return response()->json([
                    'success' => false,
                    'message' => 'Noticia no encontrada'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'content' => 'sometimes|required|string',
                'summary' => 'nullable|string|max:500',
                'type' => 'sometimes|required|in:update,feature,fix,announcement',
                'solicitada_por' => 'nullable|in:CEO,EQUIPO_DE_COORDINACION,EQUIPO_DE_VENTAS,EQUIPO_DE_CURSO,EQUIPO_DE_DOCUMENTACION,ADMINISTRACION,EQUIPO_DE_TI,EQUIPO_DE_MARKETING',
                'is_published' => 'boolean',
                'published_at' => 'nullable|date',
                'redirect' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $news->update($request->only([
                'title',
                'content',
                'summary',
                'type',
                'solicitada_por',
                'is_published',
                'published_at',
                'redirect'
            ]));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Noticia actualizada exitosamente',
                'data' => $news->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar noticia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar noticia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una noticia (admin - soft delete)
     */
    public function destroy($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            // Verificar permisos de administrador
            if (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para eliminar noticias'
                ], 403);
            }

            $news = SystemNews::find($id);

            if (!$news) {
                return response()->json([
                    'success' => false,
                    'message' => 'Noticia no encontrada'
                ], 404);
            }

            DB::beginTransaction();

            $news->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Noticia eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar noticia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar noticia',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

