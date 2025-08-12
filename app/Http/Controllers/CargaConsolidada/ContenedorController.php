<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Contenedor;

class ContenedorController extends Controller
{
    public function index(Request $request)
    {
        try {
          
            $query = Contenedor::with('pais');
      
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
        // Implementación básica
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
} 