<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
class CotizacionPagosController extends Controller
{
    private $table_contenedor_cotizacion = "contenedor_consolidado_cotizacion";
    private $table_contenedor_tipo_cliente = "contenedor_consolidado_tipo_cliente";
    private $table_contenedor_consolidado_cotizacion_coordinacion_pagos = "contenedor_consolidado_cotizacion_coordinacion_pagos";
    private $table_pagos_concept = "contenedor_consolidado_pagos_concept";

    public function getClientesDocumentacionPagos(Request $request, $idContenedor)
    {
        try {
            Log::info('getClientesDocumentacionPagos', ['idContenedor' => $idContenedor]);
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Usar consulta SQL directa para mejor control de codificación
            $sql = "
                SELECT 
                    CC.*,
                    CC.id AS id_cotizacion,
                    TC.name,
                    (
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM {$this->table_contenedor_consolidado_cotizacion_coordinacion_pagos} cccp
                        JOIN {$this->table_pagos_concept} ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = CC.id
                        AND ccp.name = 'LOGISTICA'
                    ) AS total_pagos,
                    (
                        SELECT COUNT(*)
                        FROM {$this->table_contenedor_consolidado_cotizacion_coordinacion_pagos} cccp
                        JOIN {$this->table_pagos_concept} ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_cotizacion = CC.id
                        AND ccp.name = 'LOGISTICA'
                    ) AS pagos_count
                FROM {$this->table_contenedor_cotizacion} CC
                LEFT JOIN {$this->table_contenedor_tipo_cliente} TC ON TC.id = CC.id_tipo_cliente
                WHERE CC.id_contenedor = ?
                ORDER BY CC.id ASC
            ";

            $params = [$idContenedor];
            
            // Configurar charset para la conexión
            DB::statement('SET NAMES utf8mb4');
            DB::statement('SET CHARACTER SET utf8mb4');
            DB::statement('SET character_set_connection=utf8mb4');
            
            $results = DB::select($sql, $params);

            // Aplicar filtros según el rol del usuario
            $filteredResults = collect($results);
            
            if ($user->No_Grupo == Usuario::ROL_COTIZADOR && $user->ID_Usuario != 28791) {
                $filteredResults = $filteredResults->where('id_usuario', $user->ID_Usuario);
            }

            if ($user->No_Grupo != Usuario::ROL_COTIZADOR) {
                $filteredResults = $filteredResults->where('estado_cotizador', 'CONFIRMADO');

                if ($request->Filtro_Estado && $request->Filtro_Estado != "0") {
                    $fieldToFilter = [
                        Usuario::ROL_COORDINACION => 'estado',
                        Usuario::ROL_ALMACEN_CHINA => 'estado_china',
                        Usuario::ROL_CATALOGO_CHINA => 'estado_china',
                        Usuario::ROL_DOCUMENTACION => 'estado',
                    ];
                    
                    if (isset($fieldToFilter[$user->No_Grupo])) {
                        $filteredResults = $filteredResults->where($fieldToFilter[$user->No_Grupo], $request->Filtro_Estado);
                    }
                }
            } else {
                if ($request->Filtro_Estado && $request->Filtro_Estado != "0") {
                    $filteredResults = $filteredResults->where('estado_cotizador', $request->Filtro_Estado);
                }
                $filteredResults = $filteredResults->sortBy('fecha_confirmacion');
            }

            // Ordenar por ID si no es cotizador
            if ($user->No_Grupo != Usuario::ROL_COTIZADOR) {
                $filteredResults = $filteredResults->sortBy('id');
            }

            // Procesar resultados y filtrar datos corruptos
            $data = $filteredResults
                ->filter(function ($row) {
                    // Filtrar registros con datos corruptos o vacíos
                    $nombre = $this->cleanText($row->nombre ?? '');
                    $telefono = $this->cleanText($row->telefono ?? '');
                    
                    // Excluir registros con nombres vacíos o que contengan texto corrupto
                    if (empty($nombre) || 
                        strpos($nombre, 'INFORMACIÓN') !== false || 
                        strpos($nombre, 'FACTURA') !== false ||
                        strpos($telefono, 'INFORMACIÓN') !== false ||
                        strpos($telefono, 'FACTURA') !== false) {
                        return false;
                    }
                    
                    return true;
                })
                ->values() // Reindexar el array
                ->map(function ($row, $index) {
                    // Limpiar y codificar correctamente los campos de texto
                    $nombre = $this->cleanText($row->nombre ?? '');
                    $tipoCliente = $this->cleanText($row->name ?? '');
                    
                    $estadoPago = 'PENDIENTE';
                    if ($row->pagos_count == 0) {
                        $estadoPago = 'PENDIENTE';
                    } else if ($row->total_pagos < $row->monto) {
                        $estadoPago = 'ADELANTO';
                    } else if ($row->total_pagos == $row->monto) {
                        $estadoPago = 'PAGADO';
                    } else if ($row->total_pagos > $row->monto) {
                        $estadoPago = 'SOBREPAGO';
                    }

                    // Obtener los pagos de esta cotización
                    $pagos = $this->getPagosCotizacion($row->id_cotizacion);

                    return [
                        'index' => $index + 1,
                        'nombre' => $this->cleanText(ucwords(strtolower($nombre))),
                        'documento' => $this->cleanText($row->documento ?? ''),
                        'telefono' => $this->cleanText($row->telefono ?? ''),
                        'tipo_cliente' => ucwords(strtolower($tipoCliente)),
                        'estado_pago' => $estadoPago,
                        'tipo_pago' => 'Logistica',
                        'monto' => $row->monto ?? 0,
                        'total_pagos' => $row->total_pagos ?? 0,
                        'pagos_count' => $row->pagos_count ?? 0,
                        'id_cotizacion' => $row->id_cotizacion,
                        'pagos' => $pagos
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $data->values()->toArray()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener clientes documentación pagos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene los pagos de una cotización específica
     */
    private function getPagosCotizacion($idCotizacion)
    {
        try {
            $sql = "
                SELECT 
                    cccp.id,
                    cccp.monto,
                    cccp.payment_date,
                    cccp.status,
                    cccp.banco,
                    cccp.is_confirmed,
                    cccp.voucher_url
                FROM {$this->table_contenedor_consolidado_cotizacion_coordinacion_pagos} cccp
                JOIN {$this->table_pagos_concept} ccp ON cccp.id_concept = ccp.id
                WHERE cccp.id_cotizacion = ?
                AND ccp.name = 'LOGISTICA'
                ORDER BY cccp.payment_date ASC, cccp.id ASC
            ";

            $pagos = DB::select($sql, [$idCotizacion]);
            Log::info('pagos', ['pagos' => $pagos]);
            return collect($pagos)->map(function ($pago) {
                return [
                    'id' => $pago->id,
                    'monto' => $pago->monto ?? 0,
                    'fecha_pago' => $pago->payment_date ?? null,
                    'estado' => $pago->status ?? 'PENDIENTE',
                    'is_confirmed' => $pago->is_confirmed ?? false,
                    'banco' => $this->cleanText($pago->banco ?? ''),
                    'voucher_url' => $pago->voucher_url ?? null
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::warning('Error al obtener pagos de cotización: ' . $e->getMessage(), ['id_cotizacion' => $idCotizacion]);
            return [];
        }
    }

    /**
     * Limpia y codifica correctamente el texto
     */
    private function cleanText($text)
    {
        if (empty($text)) {
            return '';
        }

        try {
            // Convertir a string si no lo es
            $text = (string) $text;
            
            // Si ya es UTF-8 válido, devolverlo tal como está
            if (mb_check_encoding($text, 'UTF-8')) {
                return $text;
            }
            
            // Intentar diferentes codificaciones
            $encodings = ['ISO-8859-1', 'Windows-1252', 'ASCII'];
            
            foreach ($encodings as $encoding) {
                try {
                    $cleanText = mb_convert_encoding($text, 'UTF-8', $encoding);
                    if (mb_check_encoding($cleanText, 'UTF-8')) {
                        return $cleanText;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            // Si nada funciona, intentar limpiar el texto
            $cleanText = iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if ($cleanText === false) {
                // Último recurso: eliminar caracteres problemáticos
                $cleanText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
                $cleanText = mb_convert_encoding($cleanText, 'UTF-8', 'UTF-8');
            }
            
            return $cleanText;
            
        } catch (\Exception $e) {
            Log::warning('Error al limpiar texto: ' . $e->getMessage(), ['text' => $text]);
            // En caso de error, devolver una cadena vacía
            return '';
        }
    }
}
