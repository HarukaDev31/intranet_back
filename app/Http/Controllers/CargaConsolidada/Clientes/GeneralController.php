<?php

namespace App\Http\Controllers\CargaConsolidada\Clientes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\TipoCliente;
use App\Models\Usuario;

class GeneralController extends Controller
{
    public function index(Request $request, $idContenedor)
    {
        // Convertir la consulta SQL de CodeIgniter a Query Builder de Laravel
        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->select([
                'CC.*',
                'C.*',
                'CC.id as id_cotizacion',
                'TC.name as name',
                'U.No_Nombres_Apellidos as asesor',
                DB::raw("CONCAT(
                    C.carga,
                    DATE_FORMAT(CC.fecha, '%d%m%y'),
                    UPPER(LEFT(TRIM(CC.nombre), 3))
                ) as COD")
            ])
            ->join('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
            ->join('contenedor_consolidado_tipo_cliente as TC', 'TC.id', '=', 'CC.id_tipo_cliente')
            ->leftJoin('usuario as U', 'U.ID_Usuario', '=', 'CC.id_usuario')
            ->where('CC.id_contenedor', $idContenedor)
            ->whereNotNull('CC.estado_cliente')
            ->where('CC.estado_cotizador', 'CONFIRMADO');

        // Aplicar filtro de estado si se proporciona
        $estado = $request->input('estado', '0');
        if ($estado !== '0') {
            $query->where('CC.estado', $estado);
        }

        // Aplicar filtros adicionales si se proporcionan
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('CC.nombre', 'LIKE', "%{$search}%")
                  ->orWhere('CC.documento', 'LIKE', "%{$search}%")
                  ->orWhere('CC.correo', 'LIKE', "%{$search}%");
            });
        }

        // Ordenamiento
        $sortField = $request->input('sort_by', 'CC.fecha');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        // Paginación
        $perPage = $request->input('per_page', 10);
        $data = $query->paginate($perPage);

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
}
