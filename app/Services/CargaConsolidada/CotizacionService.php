<?php

namespace App\Services\CargaConsolidada;

use App\Models\CargaConsolidada\Cotizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Usuario;


class CotizacionService
{
    /**
     * Obtener cotizaciones con paginación y filtros
     */
    public function obtenerCotizaciones(Request $request, $idContenedor)
    {
        $user = JWTAuth::parseToken()->authenticate();
            $query = Cotizacion::where('id_contenedor', $idContenedor);
            $rol = $user->getNombreGrupo();
            // Aplicar filtros básicos
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'LIKE', "%{$search}%")
                        ->orWhere('documento', 'LIKE', "%{$search}%")
                        ->orWhere('telefono', 'LIKE', "%{$search}%");
                });
            }

            // Filtrar por estado si se proporciona
            if ($request->has('estado') && !empty($request->estado)) {
                $query->where('estado', $request->estado);
            }

            if ($request->has('fecha_inicio')) {
                $query->whereDate('fecha', '>=', $request->fecha_inicio);
            }
            if ($request->has('fecha_fin')) {
                $query->whereDate('fecha', '<=', $request->fecha_fin);
            }
            //if request has estado_coordinacion or estado_china  then query with  proveedores  and just get cotizaciones with at least one proveedor with the state
            if ($request->has('estado_coordinacion') || $request->has('estado_china')) {
                $query->whereHas('proveedores', function ($query) use ($request) {
                    $query->where('estados', $request->estado_coordinacion)
                        ->orWhere('estados_proveedor', $request->estado_china);
                });
            }
            // Aplicar filtros según el rol del usuario
            switch ($rol) {
                case Usuario::ROL_COTIZADOR:
                    if ($user->getIdUsuario() != 28791) {
                        $query->where('id_usuario', $user->getIdUsuario());
                    }

                    break;

                case Usuario::ROL_DOCUMENTACION:
                    $query->where('estado_cotizador', 'CONFIRMADO')
                        ->whereNotNull('estado_cliente');
                    break;

                case Usuario::ROL_COORDINACION:
                    $query->where('estado_cotizador', 'CONFIRMADO')
                        ->whereNotNull('estado_cliente');
                    break;
            }
            $query->whereNull('id_cliente_importacion');
            // Ordenamiento
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortField, $sortOrder);

            // Paginación
            $perPage = $request->input('limit', 100);
            $page = $request->input('page', 1);
            $results = $query->paginate($perPage, ['*'], 'page', $page);

            $userId = auth()->id();

            // Transformar los datos para la respuesta
            $data = $results->map(function ($cotizacion) {
                return [
                    'id' => $cotizacion->id,
                    'nombre' => $cotizacion->nombre,
                    'documento' => $cotizacion->documento,
                    'telefono' => $cotizacion->telefono,
                    'correo' => $cotizacion->correo,
                    'fecha' => $cotizacion->fecha,
                    'estado' => $cotizacion->estado,
                    'estado_cliente' => $cotizacion->name,
                    'estado_cotizador' => $cotizacion->estado_cotizador,
                    'monto' => $cotizacion->monto,
                    'monto_final' => $cotizacion->monto_final,
                    'volumen' => $cotizacion->volumen,
                    'volumen_final' => $cotizacion->volumen_final,
                    'tarifa' => $cotizacion->tarifa,
                    'qty_item' => $cotizacion->qty_item,
                    'fob' => $cotizacion->fob,
                    'cotizacion_file_url' => $cotizacion->cotizacion_file_url,
                    'impuestos' => $cotizacion->impuestos,
                    'tipo_cliente' => $cotizacion->tipoCliente->name,
                ];
            });


            return [
                'data' => $data,
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage()
                ],
            ];
    }


}
