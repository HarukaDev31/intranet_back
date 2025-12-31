<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MenuController extends Controller
{
    /**
     * Create a new MenuController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * @OA\Get(
     *     path="/menu/listar",
     *     tags={"Menú"},
     *     summary="Listar menús del usuario",
     *     description="Obtiene la estructura jerárquica de menús disponibles para el usuario autenticado",
     *     operationId="listarMenu",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Menús obtenidos exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Menús obtenidos exitosamente"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="ID_Menu", type="integer"),
     *                     @OA\Property(property="No_Menu", type="string"),
     *                     @OA\Property(property="Hijos", type="array", @OA\Items(type="object"))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=500, description="Error del servidor")
     * )
     *
     * Listar menús del usuario autenticado
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listarMenu()
    {
        try {
            $user = Auth::user();
            $idGrupo = $user->ID_Grupo;
            $noUsuario = $user->No_Usuario;

            // Configurar condiciones según el usuario
            $selectDistinct = "";
            $whereIdGrupo = "AND GRPUSR.ID_Grupo = " . $idGrupo;
            $orderByNuAgregar = "";

            if ($noUsuario == 'root') {
                $selectDistinct = "DISTINCT";
                $whereIdGrupo = "";
                $orderByNuAgregar = "ORDER BY Nu_Agregar DESC";
            }

            // Obtener menús padre
            $sqlPadre = "SELECT {$selectDistinct}
                        MNU.*,
                        (SELECT COUNT(*) FROM menu WHERE ID_Padre = MNU.ID_Menu AND Nu_Activo = 0) AS Nu_Cantidad_Menu_Padre
                        FROM menu AS MNU
                        JOIN menu_acceso AS MNUACCESS ON (MNU.ID_Menu = MNUACCESS.ID_Menu)
                        JOIN grupo_usuario AS GRPUSR ON (GRPUSR.ID_Grupo_Usuario = MNUACCESS.ID_Grupo_Usuario)
                        WHERE MNU.ID_Padre = 0
                        AND MNU.Nu_Activo = 0
                        {$whereIdGrupo}
                        ORDER BY MNU.ID_Padre ASC, MNU.Nu_Orden, MNU.ID_MENU ASC";

            $arrMenuPadre = DB::select($sqlPadre);

            // Obtener hijos para cada menú padre
            foreach ($arrMenuPadre as $rowPadre) {
                $sqlHijos = "SELECT {$selectDistinct}
                            MNU.*,
                            (SELECT COUNT(*) FROM menu WHERE ID_Padre = MNU.ID_Menu AND Nu_Activo = 0) AS Nu_Cantidad_Menu_Hijos
                            FROM menu AS MNU
                            JOIN menu_acceso AS MNUACCESS ON (MNU.ID_Menu = MNUACCESS.ID_Menu)
                            JOIN grupo_usuario AS GRPUSR ON (GRPUSR.ID_Grupo_Usuario = MNUACCESS.ID_Grupo_Usuario)
                            WHERE MNU.ID_Padre = ?
                            AND MNU.Nu_Activo = 0
                            {$whereIdGrupo}
                            ORDER BY MNU.Nu_Orden";

                $rowPadre->Hijos = DB::select($sqlHijos, [$rowPadre->ID_Menu]);

                // Obtener sub-hijos para cada hijo
                foreach ($rowPadre->Hijos as $rowSubHijos) {
                    if ($rowSubHijos->Nu_Cantidad_Menu_Hijos > 0) {
                        $sqlSubHijos = "SELECT {$selectDistinct}
                                       MNU.*
                                       FROM menu AS MNU
                                       JOIN menu_acceso AS MNUACCESS ON (MNU.ID_Menu = MNUACCESS.ID_Menu)
                                       JOIN grupo_usuario AS GRPUSR ON (GRPUSR.ID_Grupo_Usuario = MNUACCESS.ID_Grupo_Usuario)
                                       WHERE MNU.ID_Padre = ?
                                       AND MNU.Nu_Activo = 0
                                       {$whereIdGrupo}
                                       ORDER BY MNU.Nu_Orden";

                        $rowSubHijos->SubHijos = DB::select($sqlSubHijos, [$rowSubHijos->ID_Menu]);
                    } else {
                        $rowSubHijos->SubHijos = [];
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Menús obtenidos exitosamente',
                'data' => $arrMenuPadre
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los menús: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener menús del usuario autenticado (alias para compatibilidad)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMenus()
    {
        return $this->listarMenu();
    }
} 