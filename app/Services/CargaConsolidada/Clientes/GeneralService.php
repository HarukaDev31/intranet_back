<?php

namespace App\Services\CargaConsolidada\Clientes;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Usuario;

class GeneralService
{
    public function obtenerClientes(Request $request, $idContenedor)
    {
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
            ->whereNull('CC.id_cliente_importacion')
            ->where('CC.estado_cotizador', 'CONFIRMADO');

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
        $sortField = $request->input('sort_by', 'CC.fecha');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);
        
        // Paginación
        $perPage = $request->input('limit', 100);
        $page = $request->input('page', 1);
        $results = $query->paginate($perPage, ['*'], 'page', $page);

        //Transformar los datos para la respuesta
        $data = $results->map(function ($cliente) {
            return [
                'id' => $cliente->id_cotizacion,
                'nombre' => $cliente->nombre,
                'documento' => $cliente->documento,
                'telefono' => $cliente->telefono,
                'correo' => $cliente->correo,
                'fecha' => Carbon::parse($cliente->fecha)->format('d/m/Y'),
                'estado' => $cliente->estado,
                'estado_cliente' => $cliente->name,
                'estado_cotizador' => $cliente->estado_cotizador,
                'asesor' => $cliente->asesor,
                'COD' => $cliente->COD,
            ];
        });
        return [
            'data' => $data,
            'pagination' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
            ],
        ];
    }

}