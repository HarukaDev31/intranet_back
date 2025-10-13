<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CargaConsolidada\Contenedor;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ContainersController extends Controller
{
    protected $TOTAL_CBM_CONSOLIDADO_2025 = 67;
    /**
     * Obtener lista de contenedores no completados para clientes externos
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Autenticar usuario con JWT external
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Obtener contenedores no completados (estado_china != CERRADO)
            $query = Contenedor::with('pais')
                ->where('estado_china', '!=', Contenedor::CONTEDOR_CERRADO)
                ->where('empresa', '!=', 1);

            // Ordenar por carga descendente
            $query->orderBy(DB::raw('CAST(carga AS UNSIGNED)'), 'desc');
            
            $contenedores = $query->get();

            // Obtener IDs de contenedores para las consultas agregadas
            $contenedorIds = $contenedores->pluck('id')->all();
            
            $cbmVendidos = [];
            $cotizacionesPorContenedor = [];
            
            if ($contenedorIds) {
                // Obtener CBM confirmado por los cotizadores (volumen de cotizaciones confirmadas)
                $cbmConfirmados = DB::table('contenedor_consolidado_cotizacion')
                    ->whereIn('id_contenedor', $contenedorIds)
                    ->where('estado_cotizador', 'CONFIRMADO')
                    ->select('id_contenedor', DB::raw('COALESCE(SUM(volumen), 0) as cbm_confirmado'))
                    ->groupBy('id_contenedor')
                    ->get();
                
                foreach ($cbmConfirmados as $cbm) {
                    $cbmVendidos[$cbm->id_contenedor] = (float)$cbm->cbm_confirmado;
                }
                
                // Verificar si el usuario tiene cotizaciones en cada contenedor usando teléfono normalizado
                // Normalizar el teléfono del usuario (remover espacios, guiones, paréntesis, puntos y +)
                $telefonoUsuario = $user->telefono ?? $user->celular ?? '';
                $telefonoNormalizado = preg_replace('/[\s\-\(\)\.\+]/', '', $telefonoUsuario);
                
                // Si empieza con 51 y tiene más de 9 dígitos, remover prefijo
                if (preg_match('/^51(\d{9})$/', $telefonoNormalizado, $matches)) {
                    $telefonoNormalizado = $matches[1];
                }
                
                if (!empty($telefonoNormalizado)) {
                    $cotizaciones = DB::table('contenedor_consolidado_cotizacion')
                        ->whereIn('id_contenedor', $contenedorIds)
                        ->where(function($query) use ($telefonoNormalizado) {
                            // Búsqueda flexible del teléfono normalizado
                            $query->whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(telefono), " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?', ["%{$telefonoNormalizado}%"])
                                // Búsqueda con prefijo 51
                                ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(telefono), " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?', ["%51{$telefonoNormalizado}%"]);
                        })
                        ->select('id_contenedor')
                        ->groupBy('id_contenedor')
                        ->pluck('id_contenedor')
                        ->toArray();
                        
                    foreach ($cotizaciones as $idContenedor) {
                        $cotizacionesPorContenedor[$idContenedor] = true;
                    }
                }
            }

            // Formatear los datos según la estructura requerida
            $data = $contenedores->map(function ($contenedor) use ($cbmVendidos, $cotizacionesPorContenedor) {
                // CBM confirmado por los cotizadores
                $cbmConfirmado = $cbmVendidos[$contenedor->id] ?? 0;
                
     
                $progress = min(($cbmConfirmado / $this->TOTAL_CBM_CONSOLIDADO_2025) * 100, 100);
                $progress = round($progress, 2); // Convertir a decimal (0-1)
                
                // Determinar status basado en el estado
                $status = 'active';
                if ($contenedor->estado_china == Contenedor::CONTEDOR_CERRADO || 
                    $contenedor->estado_documentacion == Contenedor::CONTEDOR_CERRADO) {
                    $status = 'completed';
                } elseif ($progress < 0.5) {
                    $status = 'inactive';
                }
                
                return [
                    'id' => $contenedor->id,
                    'carga' => (int)$contenedor->carga,
                    'type' => $contenedor->id_pais, // 1 = Perú, 2 = China
                    'progress' => $progress,
                    'status' => $status,
                    'userIsPresent' => isset($cotizacionesPorContenedor[$contenedor->id]),
                    'closeDate' => $contenedor->f_cierre ? Carbon::parse($contenedor->f_cierre)->format('Y-m-d') : null,
                    'shipDate' => $contenedor->f_zarpe ? Carbon::parse($contenedor->f_zarpe)->format('Y-m-d') : null,
                    'arrivalDate' => $contenedor->f_llegada ? Carbon::parse($contenedor->f_llegada)->format('Y-m-d') : null,
                    'deliveryDate' => $contenedor->f_nacionalizacion ? Carbon::parse($contenedor->f_nacionalizacion)->format('Y-m-d') : null,
                    'createdAt' => $contenedor->created_at ? Carbon::parse($contenedor->created_at)->toIso8601String() : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Contenedores obtenidos exitosamente'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener contenedores para cliente: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener contenedores',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

