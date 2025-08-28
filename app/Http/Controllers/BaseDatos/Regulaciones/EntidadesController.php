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
    public function update(Request $request, $id)
    {
        try {
            $entidad = Entidad::findOrFail($id);
            $entidad->fill($request->all());
            $entidad->save();
            return response()->json([
                'success' => true,
                'data' => $entidad
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la entidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id){
        try {
            $entidad = Entidad::find($id);
            $entidad->delete();
            return response()->json([
                'success' => true,
                'message' => 'Entidad eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la entidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
