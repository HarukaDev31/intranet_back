<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\TipoCliente;

class TipoClienteController extends Controller
{
    public function index(Request $request)
    {
        // Implementación básica
        return response()->json(['message' => 'TipoCliente index']);
    }

    public function store(Request $request)
    {
        // Implementación básica
        return response()->json(['message' => 'TipoCliente store']);
    }

    public function show($id)
    {
        // Implementación básica
        return response()->json(['message' => 'TipoCliente show']);
    }

    public function update(Request $request, $id)
    {
        // Implementación básica
        return response()->json(['message' => 'TipoCliente update']);
    }

    public function destroy($id)
    {
        // Implementación básica
        return response()->json(['message' => 'TipoCliente destroy']);
    }
} 