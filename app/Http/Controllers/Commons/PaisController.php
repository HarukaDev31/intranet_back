<?php

namespace App\Http\Controllers\Commons;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pais;
class PaisController extends Controller
{
    /**
     * @OA\Get(
     *     path="/commons/paises/dropdown",
     *     tags={"Commons"},
     *     summary="Obtener países para dropdown",
     *     description="Obtiene la lista de países formateada para usar en dropdowns",
     *     operationId="getPaisDropdown",
     *     @OA\Response(response=200, description="Países obtenidos exitosamente")
     * )
     */
    public function getPaisDropdown()
    {
        $paises = Pais::all();
        $data = [];
        foreach ($paises as $pais) {
            $data[] = ['value' => $pais->ID_Pais, 'label' => $pais->No_Pais];
        }
        return response()->json(['data' => $data, 'success' => true]);
    }
}
