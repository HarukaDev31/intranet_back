<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CargaConsolidada\Contenedor;

class AduanaController extends Controller
{
    public function viewFormularioAduana($idContenedor)
    {
        //get all data from carga_consolidada_aduana
        $query = DB::table('carga_consolidada_contenedor')
            ->select('*')
            ->where('id', $idContenedor);
        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }
    public function saveFormularioAduana(Request $request)
    {
        try {
            $idContenedor = $request->idContainer;
            $data = $request->all();
            //SAVE CONTENEDOR
            $contenedor = Contenedor::find($idContenedor);
            $contenedor->fill($data);
            $contenedor->save();
            return response()->json([
                'success' => true,
                'message' => 'Formulario de aduana guardado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar formulario de aduana: ' . $e->getMessage()
            ]);
        }
    }
}
