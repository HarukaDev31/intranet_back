<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Contenedor;

class ContenedorController extends Controller
{
    public function index(Request $request)
    {
        // Implementación básica
        return response()->json(['message' => 'Contenedor index']);
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