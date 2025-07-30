<?php

namespace App\Http\Controllers\BaseDatos\Regulaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BaseDatos\Regulaciones\Entidad;

class EntidadesController extends Controller
{
    public function getDropdown(Request $request)
    {
        try{
            $search = $request->input('search');
            $entidades = Entidad::where('nombre', 'like', "%$search%")->get();
            return response()->json([
                'success' => true,
                'data' => $entidades
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las entidades'
            ], 500);
        }
    }
    public function store(Request $request)
    {
        try {
            $entidad = Entidad::create($request->all());
            return response()->json([
                'success' => true,
                'data' => $entidad
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la entidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
