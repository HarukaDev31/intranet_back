<?php

namespace App\Http\Controllers\CargaConsolidada\Clientes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Usuario;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;

class PagosController extends Controller
{
    private $table_contenedor_cotizacion = "contenedor_consolidado_cotizacion";
    private $table_contenedor_tipo_cliente = "contenedor_consolidado_tipo_cliente";
    private $table_pagos_concept = "cotizacion_coordinacion_pagos_concept";
    private $table_contenedor_consolidado_cotizacion_coordinacion_pagos = "contenedor_consolidado_cotizacion_coordinacion_pagos";


    public function index(Request $request, $idContenedor)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->select(
                "*",
                "CC.id AS id_cotizacion",
                DB::raw("(
                SELECT IFNULL(SUM(cccp.monto), 0) 
                FROM " . $this->table_contenedor_consolidado_cotizacion_coordinacion_pagos . " cccp
                JOIN " . $this->table_pagos_concept . " ccp ON cccp.id_concept= ccp.id
                WHERE cccp.id_cotizacion = CC.id
                AND ccp.name = 'LOGISTICA'
            ) AS total_pagos"),
                DB::raw("(
                SELECT COUNT(*) 
                FROM " . $this->table_contenedor_consolidado_cotizacion_coordinacion_pagos . " cccp
                JOIN " . $this->table_pagos_concept . " ccp ON cccp.id_concept = ccp.id
                WHERE cccp.id_cotizacion = CC.id
                AND ccp.name = 'LOGISTICA'
            ) AS pagos_count")  
            )
            ->leftJoin($this->table_contenedor_tipo_cliente . ' AS TC', 'TC.id', '=', 'CC.id_tipo_cliente')
            ->where('CC.id_contenedor', $idContenedor)
            ->orderBy('CC.id', 'asc');
        // Si el usuario es "Cotizador", filtrar por el id del usuario actual
        if ($user->getNombreGrupo() == Usuario::ROL_COTIZADOR && $user->ID_Usuario != 28791) {
            $query->where($this->table_contenedor_cotizacion . '.id_usuario', $user->ID_Usuario);
            //order by fecha_confirmacion asc

        }
        if ($user->getNombreGrupo() != Usuario::ROL_COTIZADOR) {
            $query->where('estado_cotizador', 'CONFIRMADO');
        }
        if ($user->getNombreGrupo() == Usuario::ROL_COTIZADOR) {
            $query->orderBy('fecha_confirmacion', 'asc');
        }
        
        // Paginación
        $perPage = $request->get('limit', 10);
        $page = $request->get('page', 1);
        $query = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'success' => true,
            'data' => $query->items(),
            'pagination' => [
                'current_page' => $query->currentPage(),
                'last_page' => $query->lastPage(),
                'per_page' => $query->perPage(),
                'total' => $query->total()
            ]
        ]);
    }
}
