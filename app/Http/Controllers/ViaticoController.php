<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreViaticoRequest;
use App\Http\Requests\UpdateViaticoRequest;
use App\Services\ViaticoService;
use App\Models\Viatico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ViaticoController extends Controller
{
    protected $viaticoService;

    public function __construct(ViaticoService $viaticoService)
    {
        $this->viaticoService = $viaticoService;
    }

    /**
     * Obtener lista de viáticos
     */
    public function index(Request $request)
    {
        try {
            $filtros = [
                'status' => $request->get('status'),
                'fecha_inicio' => $request->get('fecha_inicio'),
                'fecha_fin' => $request->get('fecha_fin'),
                'search' => $request->get('search'),
                'sort_by' => $request->get('sort_by', 'created_at'),
                'sort_order' => $request->get('sort_order', 'desc')
            ];

            // Si no es administración, filtrar por usuario actual
            $user = auth()->user();
            $grupo = $user->grupo ?? null;
            if (!$grupo || $grupo->No_Grupo !== 'Administración') {
                $filtros['user_id'] = $user->ID_Usuario;
            }

            $query = $this->viaticoService->obtenerViaticos($filtros);

            // Paginación
            $perPage = $request->get('per_page', 10);
            $page = (int) $request->get('page', 1);
            $viaticos = $query->paginate($perPage, ['*'], 'page', $page);

            // Transformar datos
            $data = $viaticos->items();
            foreach ($data as $viatico) {
                $viatico->url_comprobante = $viatico->receipt_file 
                    ? asset('storage/' . $viatico->receipt_file)
                    : null;
                $viatico->url_payment_receipt = $viatico->payment_receipt_file 
                    ? asset('storage/' . $viatico->payment_receipt_file)
                    : null;
                $viatico->nombre_usuario = optional($viatico->usuario)->No_Nombres_Apellidos ?? 'N/A';
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $viaticos->currentPage(),
                    'last_page' => $viaticos->lastPage(),
                    'per_page' => $viaticos->perPage(),
                    'total' => $viaticos->total(),
                    'from' => $viaticos->firstItem(),
                    'to' => $viaticos->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener viáticos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener viáticos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener viáticos pendientes (para administración)
     */
    public function pendientes(Request $request)
    {
        try {
            $request->merge(['status' => 'PENDING']);
            return $this->index($request);
        } catch (\Exception $e) {
            Log::error('Error al obtener viáticos pendientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener viáticos pendientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener viáticos completados (para administración)
     */
    public function completados(Request $request)
    {
        try {
            $request->merge(['status' => 'CONFIRMED']);
            return $this->index($request);
        } catch (\Exception $e) {
            Log::error('Error al obtener viáticos completados: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener viáticos completados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo viático
     */
    public function store(StoreViaticoRequest $request)
    {
        try {
            $data = $request->validated();
            $archivo = $request->hasFile('receipt_file') 
                ? $request->file('receipt_file') 
                : null;

            $viatico = $this->viaticoService->crearViatico($data, $archivo);

            $viatico->url_comprobante = $viatico->receipt_file 
                ? asset('storage/' . $viatico->receipt_file)
                : null;
            $viatico->url_payment_receipt = $viatico->payment_receipt_file 
                ? asset('storage/' . $viatico->payment_receipt_file)
                : null;
            $viatico->nombre_usuario = optional($viatico->usuario)->No_Nombres_Apellidos ?? 'N/A';

            return response()->json([
                'success' => true,
                'message' => 'Viático creado exitosamente',
                'data' => $viatico
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear viático: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear viático: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un viático por ID
     */
    public function show($id)
    {
        try {
            $viatico = $this->viaticoService->obtenerViaticoPorId($id);

            if (!$viatico) {
                return response()->json([
                    'success' => false,
                    'message' => 'Viático no encontrado'
                ], 404);
            }

            // Verificar permisos: solo el usuario o administración puede ver
            $user = auth()->user();
            $grupo = $user->grupo ?? null;
            $isAdmin = $grupo && $grupo->No_Grupo === 'Administración';

            if (!$isAdmin && $viatico->user_id !== $user->ID_Usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para ver este viático'
                ], 403);
            }

            $viatico->url_comprobante = $viatico->receipt_file 
                ? asset('storage/' . $viatico->receipt_file)
                : null;
            $viatico->url_payment_receipt = $viatico->payment_receipt_file 
                ? asset('storage/' . $viatico->payment_receipt_file)
                : null;
            $viatico->nombre_usuario = optional($viatico->usuario)->No_Nombres_Apellidos ?? 'N/A';

            return response()->json([
                'success' => true,
                'data' => $viatico
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener viático: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener viático: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un viático
     */
    public function update(UpdateViaticoRequest $request, $id)
    {
        try {
            $viatico = $this->viaticoService->obtenerViaticoPorId($id);

            if (!$viatico) {
                return response()->json([
                    'success' => false,
                    'message' => 'Viático no encontrado'
                ], 404);
            }

            // Verificar permisos: solo administración puede editar
            $user = auth()->user();
            $grupo = $user->grupo ?? null;
            $isAdmin = $grupo && $grupo->No_Grupo === 'Administración';

            if (!$isAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para editar este viático'
                ], 403);
            }

            // Si el estado es CONFIRMED, solo se puede subir/eliminar archivo o cambiar estado
            if ($viatico->status === Viatico::STATUS_CONFIRMED && !$request->hasFile('receipt_file') && !$request->has('delete_file')) {
                // Permitir cambiar estado incluso si está confirmado
            }

            $data = $request->validated();
            $archivo = $request->hasFile('receipt_file') 
                ? $request->file('receipt_file') 
                : null;

            // Si se envía delete_file, eliminar el archivo
            if ($request->has('delete_file') && $request->delete_file == true) {
                $data['delete_file'] = true;
            }

            $viatico = $this->viaticoService->actualizarViatico($viatico, $data, $archivo);

            $viatico->url_comprobante = $viatico->receipt_file 
                ? asset('storage/' . $viatico->receipt_file)
                : null;
            $viatico->url_payment_receipt = $viatico->payment_receipt_file 
                ? asset('storage/' . $viatico->payment_receipt_file)
                : null;
            $viatico->nombre_usuario = optional($viatico->usuario)->No_Nombres_Apellidos ?? 'N/A';

            return response()->json([
                'success' => true,
                'message' => 'Viático actualizado exitosamente',
                'data' => $viatico
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar viático: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar viático: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un viático
     */
    public function destroy($id)
    {
        try {
            $viatico = $this->viaticoService->obtenerViaticoPorId($id);

            if (!$viatico) {
                return response()->json([
                    'success' => false,
                    'message' => 'Viático no encontrado'
                ], 404);
            }

            // Verificar permisos: solo administración puede eliminar
            $user = auth()->user();
            $grupo = $user->grupo ?? null;
            $isAdmin = $grupo && $grupo->No_Grupo === 'Administración';

            if (!$isAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar este viático'
                ], 403);
            }

            $this->viaticoService->eliminarViatico($viatico);

            return response()->json([
                'success' => true,
                'message' => 'Viático eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar viático: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar viático: ' . $e->getMessage()
            ], 500);
        }
    }
}
