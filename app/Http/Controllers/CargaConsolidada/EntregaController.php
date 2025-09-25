<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class EntregaController extends Controller
{
    public function getHeaders($idContenedor)
    {
        $contenedor = DB::table('carga_consolidada_contenedor')
            ->select('carga')
            ->where('id', $idContenedor)
            ->first();

        if (!$contenedor) {
            return response()->json(['message' => 'Contenedor no encontrado'], 404);
        }

        return response()->json(['data' => $contenedor, 'success' => true]);
    }
    public function getClientesEntrega(Request $request, $idContenedor)
    {
        // Lo obtiene de la tabla de clientes asociados al contenedor
        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
            ->select('C.*','CC.*')
            ->where('CC.id_contenedor', $idContenedor);
        // Aplicar filtro de estado si se proporciona
        $page = $request->input('currentPage', 1);
        $perPage = $request->input('itemsPerPage', 100);
        $clientes = $query->paginate($perPage, ['*'], 'currentPage', $page);
        // Aplicar filtros adicionales si se proporcionan
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('CC.nombre', 'LIKE', "%{$search}%")
                    ->orWhere('CC.documento', 'LIKE', "%{$search}%")
                    ->orWhere('CC.correo', 'LIKE', "%{$search}%");
            });
        }
        // Ordenamiento
        $sortField = $request->input('sort_by', 'CC.id');
        $sortOrder = $request->input('sort_order', 'asc');

        // Paginación
        $data = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $data->items(),
            'success' => true,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem()
            ]
        ]);
    }
    public function sendForm($idContenedor)
    {
        // Lógica para manejar el envío del formulario de entrega
    }
    public function getEntregas(Request $request, $idContenedor)
    {
        // Lo obtiene de la tabla de clientes asociados al contenedor
        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
            ->select('C.*','CC.*')
            ->where('CC.id_contenedor', $idContenedor);
        // Aplicar filtro de estado si se proporciona
        $page = $request->input('currentPage', 1);
        $perPage = $request->input('itemsPerPage', 100);
        $clientes = $query->paginate($perPage, ['*'], 'currentPage', $page);
        // Aplicar filtros adicionales si se proporcionan
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('CC.nombre', 'LIKE', "%{$search}%")
                    ->orWhere('CC.documento', 'LIKE', "%{$search}%")
                    ->orWhere('CC.correo', 'LIKE', "%{$search}%");
            });
        }
        // Ordenamiento
        $sortField = $request->input('sort_by', 'CC.id');
        $sortOrder = $request->input('sort_order', 'asc');

        // Paginación
        $data = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $data->items(),
            'success' => true,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem()
            ]
        ]);
    }
    public function getEntregasDetalle($idEntrega)
    {
        // Lógica para obtener los detalles de una entrega específica
    }
}