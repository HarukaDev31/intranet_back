<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;

class FacturaGuiaController extends Controller
{
    public function getContenedorFacturaGuia(Request $request, $idContenedor)
    {
        $perPage = $request->input('per_page', 10);
        
        $query = Cotizacion::select('contenedor_consolidado_cotizacion.*', 
                                'contenedor_consolidado_tipo_cliente.*',
                                'contenedor_consolidado_cotizacion.id as id_cotizacion')
            ->join('contenedor_consolidado_tipo_cliente', 
                  'contenedor_consolidado_cotizacion.id_tipo_cliente', '=', 
                  'contenedor_consolidado_tipo_cliente.id')
            ->where('id_contenedor', $idContenedor)
            ->whereNotNull('estado_cliente')
            ->paginate($perPage);

        return response()->json([
            'data' => $query->items(),
            'pagination' => [
                'total' => $query->total(),
                'per_page' => $query->perPage(),
                'current_page' => $query->currentPage(),
                'last_page' => $query->lastPage(),
                'from' => $query->firstItem(),
                'to' => $query->lastItem()
            ],
            'success' => true
        ]);
    }
}
