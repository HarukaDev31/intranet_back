<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;

class FacturaGuiaController extends Controller
{
    public function getContenedorFacturaGuia(Request $request, $idContenedor)
    {
        $perPage = $request->input('per_page', 10);

        $query = Cotizacion::select(
            'contenedor_consolidado_cotizacion.*',
            'contenedor_consolidado_tipo_cliente.*',
            'contenedor_consolidado_cotizacion.id as id_cotizacion'
        )
            ->join(
                'contenedor_consolidado_tipo_cliente',
                'contenedor_consolidado_cotizacion.id_tipo_cliente',
                '=',
                'contenedor_consolidado_tipo_cliente.id'
            )
            ->where('id_contenedor', $idContenedor)
            ->whereNotNull('estado_cliente')
            ->whereNull('id_cliente_importacion')
            ->where('estado_cotizador',"CONFIRMADO")

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
    public function uploadGuiaRemision(Request $request)
    {
        try {
            $idContenedor = $request->idCotizacion;
            $file = $request->file('file');
            $file->storeAs('cargaconsolidada/guiaremision/' . $idContenedor, $file->getClientOriginalName());
            //update guia remision url
            $cotizacion = Cotizacion::find($idContenedor);
            $cotizacion->guia_remision_url = $file->getClientOriginalName();
            $cotizacion->save();
            return response()->json([
                'success' => true,
                'message' => 'Guia remision actualizada correctamente',
                'path' => $file->getClientOriginalName()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar guia remision: ' . $e->getMessage()
            ]);
        }
    }
    public function uploadFacturaComercial(Request $request)
    {
        try {
            $idContenedor = $request->idCotizacion;
            $file = $request->file('file');
            $file->storeAs('cargaconsolidada/facturacomercial/' . $idContenedor, $file->getClientOriginalName());
            //update factura comercial 
            $cotizacion = Cotizacion::find($idContenedor);
            $cotizacion->factura_comercial = $file->getClientOriginalName();
            $cotizacion->save();
            return response()->json([
                'success' => true,
                'message' => 'Factura comercial actualizada correctamente',
                'path' => $file->getClientOriginalName()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar factura comercial: ' . $e->getMessage()
            ]);
        }
    }
    //create function to get headers data get empty headers but carga 
    public function getHeadersData($idContenedor)
    {
        try {
            $contenedor = Contenedor::where('id', $idContenedor)->first();
            $headers = [];
            return response()->json([
                'success' => true,
                'data' => $headers,
                'carga' => $contenedor->carga ?? ''
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener headers: ' . $e->getMessage()
            ]);
        }
    }
    public function deleteFacturaComercial($idContenedor)
    {
        $cotizacion = Cotizacion::find($idContenedor);
        if($cotizacion->factura_comercial){
            $path = storage_path('app/' . $cotizacion->factura_comercial);
            if(file_exists($path)){
                unlink($path);
            }
        }else{
            return response()->json([
                'success' => false,
                'message' => 'Factura comercial no encontrada'
            ]);
        }
        $cotizacion->factura_comercial = null;
        $cotizacion->save();
        return response()->json([
            'success' => true,
            'message' => 'Factura comercial eliminada correctamente'
        ]);
    }
    public function deleteGuiaRemision($idContenedor)
    {
        $cotizacion = Cotizacion::find($idContenedor);
        if($cotizacion->guia_remision_url){
            $path = storage_path('app/' . $cotizacion->guia_remision_url);
            if(file_exists($path)){
                unlink($path);
            }
        }else{
            return response()->json([
                'success' => false,
                'message' => 'Guia remision no encontrada'
            ]);
        }
        $cotizacion->guia_remision_url = null;
        $cotizacion->save();
        return response()->json([
            'success' => true,
            'message' => 'Guia remision eliminada correctamente'
        ]);
    }
}
