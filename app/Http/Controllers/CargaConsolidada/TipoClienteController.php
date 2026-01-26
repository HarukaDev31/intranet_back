<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\TipoCliente;

class TipoClienteController extends Controller
{
    /**
     * @OA\Get(
     *     path="/carga-consolidada/tipos-cliente",
     *     tags={"Tipos de Cliente"},
     *     summary="Listar tipos de cliente",
     *     description="Obtiene la lista de tipos de cliente disponibles",
     *     operationId="getTiposCliente",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Tipos de cliente obtenidos exitosamente")
     * )
     */
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