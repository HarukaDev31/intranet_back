<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignController extends Controller
{
    private $table_campana_curso_dias = 'campana_curso_dias';

    public function store(Request $request)
    {
        try {
            // Validar los datos del request
            $request->validate([
                'Fe_Inicio' => 'required|date',
                'Fe_Fin' => 'required|date|after:Fe_Inicio',
                'Dias_Seleccionados' => 'required'
            ]);

            $fe_inicio = $request->Fe_Inicio;
            $fe_fin = $request->Fe_Fin;
            $dias = $request->Dias_Seleccionados;

            // Insertar campaña
            $data = [
                'Fe_Inicio'   => $fe_inicio,
                'Fe_Fin'      => $fe_fin,
                'Fe_Creacion' => now()
            ];

            $id = DB::table('campana_curso')->insertGetId($data);

            if ($id) {
                // Obtener la campaña recién creada
                $campana = DB::table('campana_curso as c')
                    ->select([
                        'c.ID_Campana',
                        'c.Fe_Creacion',
                        'c.Fe_Inicio',
                        'c.Fe_Fin',
                        DB::raw('MONTH(c.Fe_Inicio) as Mes_Numero'),
                        DB::raw('(SELECT COUNT(*) FROM pedido_curso p WHERE p.ID_Campana = c.ID_Campana) as cantidad_personas')
                    ])
                    ->where('c.ID_Campana', $id)
                    ->first();

                // Traduce el mes a español
                $meses_es = [
                    1 => 'Enero',
                    2 => 'Febrero',
                    3 => 'Marzo',
                    4 => 'Abril',
                    5 => 'Mayo',
                    6 => 'Junio',
                    7 => 'Julio',
                    8 => 'Agosto',
                    9 => 'Septiembre',
                    10 => 'Octubre',
                    11 => 'Noviembre',
                    12 => 'Diciembre'
                ];
                $no_campana = $meses_es[(int)$campana->Mes_Numero];

                // Armar array por posición (igual que en getCampanas)
                $data_row = [
                    $campana->ID_Campana,
                    $campana->Fe_Creacion,
                    $no_campana,
                    $campana->Fe_Inicio,
                    $campana->Fe_Fin,
                    $campana->cantidad_personas,
                    '<div>
                        <i class="fas fa-eye text-primary view-eye" style="cursor:pointer; padding:10px;"></i>
                        <i class="fas fa-trash text-danger" style="cursor:pointer; padding:10px;" onclick="borrarCampana(\'' . $campana->ID_Campana . '\')"></i>
                    </div>'
                ];

                // Eliminar días existentes e insertar nuevos días
                DB::table($this->table_campana_curso_dias)
                    ->where('id_campana', $id)
                    ->delete();

                $dias_array = $dias;

                if (is_array($dias_array)) {
                    foreach ($dias_array as $dia) {
                        Log::info('Día a insertar: ' . $dia);
                        Log::info('ID de campaña: ' . $id);

                        $data_dia = [
                            'id_campana' => $id,
                            'fecha' => $dia
                        ];

                        DB::table($this->table_campana_curso_dias)->insert($data_dia);
                    }
                }

                return response()->json([
                    'message' => 'Campaña registrada correctamente',
                    'success' => true,
                    'row' => $data_row // <-- array por posición
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudo registrar la campaña'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en crearCampana: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear la campaña: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {

            // Obtener campañas desde la base de datos
            $campanas = DB::table('campana_curso as c')
                ->select([
                    'c.ID_Campana',
                    'c.Fe_Creacion',
                    'c.Fe_Inicio',
                    'c.Fe_Fin',
                    DB::raw('MONTH(c.Fe_Inicio) as Mes_Numero'),
                    DB::raw('(SELECT COUNT(*) FROM pedido_curso p WHERE p.ID_Campana = c.ID_Campana) as cantidad_personas')
                ])
                ->orderBy('c.ID_Campana', 'desc')
                ->get();

            $data = [];

            // Traduce el mes a español
            $meses_es = [
                1 => 'Enero',
                2 => 'Febrero',
                3 => 'Marzo',
                4 => 'Abril',
                5 => 'Mayo',
                6 => 'Junio',
                7 => 'Julio',
                8 => 'Agosto',
                9 => 'Septiembre',
                10 => 'Octubre',
                11 => 'Noviembre',
                12 => 'Diciembre'
            ];

            //map to data to format date to text
            $data = $campanas->map(function ($campana) use ($meses_es) {
                $no_campana = $meses_es[(int)$campana->Mes_Numero];
                return [
                    'ID_Campana' => $campana->ID_Campana,
                    'Fe_Creacion' => date('d/m/Y', strtotime($campana->Fe_Creacion)),
                    'No_Campana' => $no_campana,
                    'Fe_Inicio' => date('d/m/Y', strtotime($campana->Fe_Inicio)),
                    'Fe_Fin' => date('d/m/Y', strtotime($campana->Fe_Fin)),
                    'cantidad_personas' => $campana->cantidad_personas,
                   
                ];
            });
            $data = $data->toArray();

            return response()->json([
                'data' => $data,
                'success' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Error en index (getCampanasTabla): ' . $e->getMessage());
            return response()->json([
                'data' => [],
                'success' => false,
                'message' => 'Error al obtener las campañas: ' . $e->getMessage()
            ], 500);
        }
    }
    public function destroy($id)
    {
        try {
            // Iniciar transacción para asegurar consistencia
            DB::beginTransaction();
            
            // Primero eliminar los días relacionados
            DB::table($this->table_campana_curso_dias)
                ->where('id_campana', $id)
                ->delete();
            
            // Luego eliminar la campaña
            $deleted = DB::table('campana_curso')
                ->where('ID_Campana', $id)
                ->delete();
            
            if ($deleted > 0) {
                DB::commit();
                return response()->json([
                    'success' => true, 
                    'message' => 'Campaña eliminada correctamente'
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false, 
                    'message' => 'No se encontró la campaña a eliminar'
                ], 404);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en destroy (eliminar campaña): ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Error al eliminar la campaña: ' . $e->getMessage()
            ], 500);
        }
    }
}
