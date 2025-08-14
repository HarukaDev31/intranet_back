<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\ContenedorPasos;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;

class ContenedorController extends Controller
{
    public function index(Request $request)
    {
        try {

            $query = Contenedor::with('pais');
            $currentUser = Auth::user();
            $completado = $request->completado ?? false;
            if ($currentUser->rol == Usuario::ROL_DOCUMENTACION) {
                if ($completado) {
                    $query->where('estado_documentacion', '=', Contenedor::CONTEDOR_CERRADO);
                } else {
                    $query->where('estado_documentacion', '!=', Contenedor::CONTEDOR_CERRADO);
                }
            } else {
                if ($completado) {
                    $query->where('estado_china', '=', Contenedor::CONTEDOR_CERRADO);
                } else {
                    $query->where('estado_china', '!=', Contenedor::CONTEDOR_CERRADO);
                }
            }
            $data = $query->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $data->items(),
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ]

            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener contenedores: ' . $e->getMessage()
            ], 500);
        }
    }


    public function store(Request $request)
    {
        return response()->json(['message' => 'Contenedor store']);
    }

    public function show($id)
    {
        // Implementación básica
        return response()->json(['message' => 'Contenedor show']);
    }

    public function update(Request $request, $id)
    {
        // Implementación básica
        return response()->json(['message' => 'Contenedor update']);
    }

    public function destroy($id)
    {
        // Implementación básica
        return response()->json(['message' => 'Contenedor destroy']);
    }

    public function filterOptions()
    {
        // Implementación básica
        return response()->json(['message' => 'Contenedor filter options']);
    }
    public function getContenedorPasos($idContenedor)
    {
        try {
            $user = Auth::user();
            $query = ContenedorPasos::where('id_pedido', $idContenedor)->orderBy('id_order', 'asc');
            switch ($user->rol) {
                case Usuario::ROL_COORDINACION:
                    $query->limit(5);
                    break;

                case Usuario::ROL_COTIZADOR:
                    if ($user->id_usuario == 28791) {
                        $query->limit(2);
                    }
                    $query->limit(1);
                    break;
                default:
                    $query->limit(1);
                    break;
            }
            $data = $query->select('id', 'name', 'status', 'iconURL')->get();
            return response()->json(['data' => $data, 'success' => true]);
        } catch (\Exception $e) {
            return response()->json(['data' => [], 'success' => false, 'message' => 'Error al obtener pasos del contenedor: ' . $e->getMessage()]);
        }
    }
}
