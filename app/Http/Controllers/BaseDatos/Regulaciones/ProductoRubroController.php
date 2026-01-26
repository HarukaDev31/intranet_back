<?php

namespace App\Http\Controllers\BaseDatos\Regulaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BaseDatos\Regulaciones\ProductoRubro;
use Illuminate\Support\Facades\Log;
class ProductoRubroController extends Controller
{
    /**
     * @OA\Get(
     *     path="/regulaciones/rubros/dropdown",
     *     tags={"Regulaciones"},
     *     summary="Obtener rubros para dropdown",
     *     description="Obtiene la lista de rubros de productos para usar en dropdowns",
     *     operationId="getRubrosDropdown",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="tipo", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Rubros obtenidos exitosamente")
     * )
     */
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
    public function update(Request $request, $id)
    {
        try {
            $rubro = ProductoRubro::findOrFail($id);
            // Actualiza solo los campos que sean enviados
            $rubro->fill($request->only(['nombre', 'tipo', 'descripcion'])); // ajustar campos según modelo
            $rubro->save();

            Log::info('ProductoRubro updated', ['id' => $id, 'data' => $request->all()]);

            return response()->json([
                'success' => true,
                'data' => $rubro
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating ProductoRubro: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el rubro',
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
