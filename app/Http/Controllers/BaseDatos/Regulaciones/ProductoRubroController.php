<?php

namespace App\Http\Controllers\BaseDatos\Regulaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BaseDatos\Regulaciones\ProductoRubro;
use Illuminate\Support\Facades\Log;
class ProductoRubroController extends Controller
{
    public function getDropdown(Request $request)
    {
        try {
            $search = $request->input('search');
            $tipo = $request->input('tipo');
            $rubros = ProductoRubro::where('nombre', 'like', "%$search%")->where('tipo', $tipo)->get();
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
            Log::info($request->all());
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
    public function destroy($id){
        try {
            $rubro = ProductoRubro::find($id);
            $rubro->delete();
            return response()->json([
                'success' => true,
                'message' => 'Rubro eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el rubro',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
