<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;

class CotizacionController extends Controller
{
    public function index(Request $request)
    {
        // Implementación básica
        return response()->json(['message' => 'Cotizacion index']);
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
} 