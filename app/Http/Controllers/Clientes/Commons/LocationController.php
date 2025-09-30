<?php

namespace App\Http\Controllers\Clientes\Commons;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Departamento;
use App\Models\Provincia;
use App\Models\Distrito;
    
class LocationController extends Controller
{
    /**
     * Obtiene todos los departamentos
     */
    public function getDepartamentos()
    {
        try {
            $departamentos = Departamento::select('ID_Departamento as id', 'No_Departamento as nombre')
                ->orderBy('No_Departamento')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $departamentos,
                'message' => 'Departamentos obtenidos correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene todas las provincias de un departamento específico
     */
    public function     getProvincias($idDepartamento)
    {
        try {
            $provincias = Provincia::where('ID_Departamento', $idDepartamento)
                ->select('ID_Provincia as id', 'No_Provincia as nombre', 'ID_Departamento as id_departamento')
                ->orderBy('No_Provincia')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $provincias,
                'message' => 'Provincias obtenidas correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene todos los distritos de una provincia específica
     */
    public function getDistritos($idProvincia)
    {
        try {
            $distritos = Distrito::where('ID_Provincia', $idProvincia)
                ->select('ID_Distrito as id', 'No_Distrito as nombre', 'ID_Provincia as id_provincia')
                ->orderBy('No_Distrito')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $distritos,
                'message' => 'Distritos obtenidos correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene la estructura completa de ubicaciones (departamento -> provincia -> distrito)
     */
    public function getUbicacionCompleta($idDistrito)
    {
        try {
            $distrito = Distrito::with(['provincia.departamento'])
                ->find($idDistrito);
            
            if (!$distrito) {
                return response()->json([
                    'success' => false,
                    'error' => 'Distrito no encontrado'
                ], 404);
            }
            
            $ubicacion = [
                'distrito' => [
                    'id' => $distrito->ID_Distrito,
                    'nombre' => $distrito->No_Distrito
                ],
                'provincia' => [
                    'id' => $distrito->provincia->ID_Provincia,
                    'nombre' => $distrito->provincia->No_Provincia
                ],
                'departamento' => [
                    'id' => $distrito->provincia->departamento->ID_Departamento,
                    'nombre' => $distrito->provincia->departamento->No_Departamento
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $ubicacion,
                'message' => 'Ubicación completa obtenida correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca ubicaciones por nombre (departamento, provincia o distrito)
     */
    public function buscarUbicaciones(Request $request)
    {
        try {
            $termino = $request->get('q', '');
            
            if (empty($termino)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Término de búsqueda requerido'
                ], 400);
            }
            
            $resultados = [];
            
            // Buscar departamentos
            $departamentos = Departamento::where('No_Departamento', 'LIKE', "%{$termino}%")
                ->select('ID_Departamento as id', 'No_Departamento as nombre')
                ->limit(10)
                ->get();
            
            foreach ($departamentos as $departamento) {
                $resultados[] = [
                    'tipo' => 'departamento',
                    'id' => $departamento->id,
                    'nombre' => $departamento->nombre,
                    'ruta_completa' => $departamento->nombre
                ];
            }
            
            // Buscar provincias
            $provincias = Provincia::with('departamento')
                ->where('No_Provincia', 'LIKE', "%{$termino}%")
                ->select('ID_Provincia as id', 'No_Provincia as nombre', 'ID_Departamento')
                ->limit(10)
                ->get();
            
            foreach ($provincias as $provincia) {
                $resultados[] = [
                    'tipo' => 'provincia',
                    'id' => $provincia->id,
                    'nombre' => $provincia->nombre,
                    'ruta_completa' => $provincia->departamento->No_Departamento . ' - ' . $provincia->nombre
                ];
            }
            
            // Buscar distritos
            $distritos = Distrito::with(['provincia.departamento'])
                ->where('No_Distrito', 'LIKE', "%{$termino}%")
                ->select('ID_Distrito as id', 'No_Distrito as nombre', 'ID_Provincia')
                ->limit(10)
                ->get();
            
            foreach ($distritos as $distrito) {
                $resultados[] = [
                    'tipo' => 'distrito',
                    'id' => $distrito->id,
                    'nombre' => $distrito->nombre,
                    'ruta_completa' => $distrito->provincia->departamento->No_Departamento . ' - ' . 
                                      $distrito->provincia->No_Provincia . ' - ' . 
                                      $distrito->nombre
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $resultados,
                'message' => 'Búsqueda completada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
