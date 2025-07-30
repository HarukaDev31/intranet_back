<?php

namespace App\Http\Controllers\BaseDatos\Regulaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BaseDatos\Regulaciones\ProductoRubro;

class ProductoRubroController extends Controller
{
    public function getDropdown(Request $request)
    {
        try {
            $search = $request->input('search');
            $rubros = ProductoRubro::where('nombre', 'like', "%$search%")->get();
            return response()->json([
                'success' => true,
                'data' => $rubros
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los rubros',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function store(Request $request)
    {
        try {
            $rubro = ProductoRubro::create($request->all());
            return response()->json([
                'success' => true,
                'data' => $rubro
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el rubro',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function index()
    {
        try {
            $rubros = ProductoRubro::all();
            return response()->json([
                'success' => true,
                'data' => $rubros
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los rubros',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
