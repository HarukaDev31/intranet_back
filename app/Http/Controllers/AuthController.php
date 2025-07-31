<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\Usuario;
use App\Models\Empresa;
use App\Models\Organizacion;
use App\Models\Almacen;
use App\Helpers\CodeIgniterEncryption;
use App\Http\Controllers\MenuController;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {

        try {

            $data = $request->all();
            $result = $this->verificarAccesoLogin($data);

            if ($result['sStatus'] === 'success') {
                // Buscar el usuario para generar el token
                $usuario = Usuario::where('No_Usuario', $data['No_Usuario'])
                    ->where('Nu_Estado', 1)
                    ->first();

                if ($usuario) {
                    try {
                        $token = JWTAuth::fromUser($usuario);

                        // Cargar relaciones del usuario
                        $usuario->load(['grupo', 'empresa', 'organizacion']);

                        // Obtener menús del usuario
                        $menus = $this->obtenerMenusUsuario($usuario);

                        // Preparar información del grupo
                        $grupoInfo = null;
                        if ($usuario->grupo) {
                            $grupoInfo = [
                                'id' => $usuario->grupo->ID_Grupo,
                                'nombre' => $usuario->grupo->No_Grupo,
                                'descripcion' => $usuario->grupo->No_Grupo_Descripcion,
                                'tipo_privilegio' => $usuario->grupo->Nu_Tipo_Privilegio_Acceso,
                                'estado' => $usuario->grupo->Nu_Estado,
                                'notificacion' => $usuario->grupo->Nu_Notificacion
                            ];
                        }

                        return response()->json([
                            'status' => 'success',
                            'message' => $result['sMessage'],
                            'token' => $token,
                            'token_type' => 'bearer',
                            'expires_in' => config('jwt.ttl') * 60,
                            'user' => [
                                'id' => $usuario->ID_Usuario,
                                'nombre' => $usuario->No_Usuario,
                                'nombres_apellidos' => $usuario->No_Nombres_Apellidos,
                                'email' => $usuario->Txt_Email,
                                'estado' => $usuario->Nu_Estado,
                                'empresa' => $usuario->empresa ? [
                                    'id' => $usuario->empresa->ID_Empresa,
                                    'nombre' => $usuario->empresa->No_Empresa
                                ] : null,
                                'organizacion' => $usuario->organizacion ? [
                                    'id' => $usuario->organizacion->ID_Organizacion,
                                    'nombre' => $usuario->organizacion->No_Organizacion
                                ] : null,
                                'grupo' => $grupoInfo
                            ],
                            'iCantidadAcessoUsuario' => $result['iCantidadAcessoUsuario'] ?? null,
                            'iIdEmpresa' => $result['iIdEmpresa'] ?? null,
                            'menus' => $menus
                        ]);
                    } catch (JWTException $e) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'No se pudo crear el token'
                        ], 500);
                    }
                }
            }

            return response()->json([
                'status' => $result['sStatus'],
                'message' => $result['sMessage']
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al iniciar sesión'
            ], 500);
        }
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json([
            'status' => 'success',
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            $token = JWTAuth::refresh();
            return $this->respondWithToken($token);
        } catch (JWTException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo refrescar el token'
            ], 500);
        }
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60
        ]);
    }

    /**
     * Verificar acceso de login basado en la query original
     */
    public function verificarAccesoLogin($data)
    {
        $No_Password = trim($data['No_Password']);
        $No_Password = strip_tags($No_Password);

        $No_Usuario = trim($data['No_Usuario']);
        $No_Usuario = strip_tags($No_Usuario);

        // Buscar usuario
        $usuario = DB::table('usuario')
            ->where('No_Usuario', $No_Usuario)
            ->orderBy('Fe_Creacion', 'ASC')
            ->first();

        if (!$usuario) {
            return [
                'sStatus' => 'danger',
                'sMessage' => 'No existe usuario'
            ];
        }

        // Verificar estado de empresa
        $empresa = DB::table('empresa')
            ->where('ID_Empresa', $usuario->ID_Empresa)
            ->first();

        if (!$empresa || $empresa->Nu_Estado != 1) {
            return [
                'sStatus' => 'danger',
                'sMessage' => 'Comunicarse con soporte'
            ];
        }

        // Verificar estado de organización
        $organizacion = DB::table('organizacion')
            ->where('ID_Empresa', $usuario->ID_Empresa)
            ->where('ID_Organizacion', $usuario->ID_Organizacion)
            ->first();

        if (!$organizacion || $organizacion->Nu_Estado != 1) {
            return [
                'sStatus' => 'danger',
                'sMessage' => 'Comunicarse con soporte'
            ];
        }

        // Verificar contraseña usando encriptación de CodeIgniter
        $ciEncryption = new CodeIgniterEncryption();
        if (!$ciEncryption->verifyPassword($No_Password, $usuario->No_Password)) {
            return [
                'sStatus' => 'warning',
                'sMessage' => 'Contraseña incorrecta'
            ];
        }

        // Verificar estado del usuario
        if ($usuario->Nu_Estado != 1) {
            return [
                'sStatus' => 'warning',
                'sMessage' => 'Comunicarse con soporte'
            ];
        }

        $ID_Empresa = isset($data['ID_Empresa']) ? trim($data['ID_Empresa']) : '';
        $ID_Organizacion = isset($data['ID_Organizacion']) ? trim($data['ID_Organizacion']) : '';

        if ($ID_Empresa == '' && $ID_Organizacion == '') {
            // Obtener información completa del usuario
            $usuarioCompleto = DB::select("
                SELECT
                    USR.*,
                    GRP.ID_Organizacion,
                    GRP.No_Grupo,
                    GRP.No_Grupo_Descripcion,
                    GRP.Nu_Tipo_Privilegio_Acceso,
                    GRP.Nu_Notificacion,
                    GRPUSR.ID_Grupo_Usuario,
                    T.No_Dominio_Tienda_Virtual,
                    T.No_Subdominio_Tienda_Virtual,
                    T.Nu_Estado as TiendaEstado,
                    P.ID_Pais,
                    P.No_Pais,
                    MONE.*
                FROM
                    usuario AS USR
                    JOIN empresa AS EMP ON(EMP.ID_Empresa = USR.ID_Empresa)
                    JOIN pais AS P ON(P.ID_Pais = EMP.ID_Pais)
                    JOIN moneda AS MONE ON(EMP.ID_Empresa = MONE.ID_Empresa)
                    JOIN grupo_usuario AS GRPUSR ON(USR.ID_Usuario = GRPUSR.ID_Usuario)
                    JOIN grupo AS GRP ON(GRP.ID_Grupo = GRPUSR.ID_Grupo)
                    LEFT JOIN subdominio_tienda_virtual T ON T.ID_Empresa=USR.ID_Empresa
                WHERE
                    USR.No_Usuario = ? AND USR.Nu_Estado=1 
                ORDER BY Fe_Creacion ASC LIMIT 1
            ", [$No_Usuario]);

            if (empty($usuarioCompleto)) {
                return [
                    'sStatus' => 'warning',
                    'sMessage' => 'Comunicarse con soporte para activación de cuenta'
                ];
            }

            return [
                'sStatus' => 'success',
                'sMessage' => 'Iniciando sesión',
                'iCantidadAcessoUsuario' => 1,
                'iIdEmpresa' => $usuarioCompleto[0]->ID_Empresa
            ];
        } else {
            // Verificar acceso específico a empresa y organización
            $usuarioEspecifico = DB::select("
                SELECT
                    USR.*,
                    GRP.ID_Organizacion,
                    GRP.No_Grupo,
                    GRP.No_Grupo_Descripcion,
                    GRP.Nu_Tipo_Privilegio_Acceso,
                    GRP.Nu_Notificacion,
                    GRPUSR.ID_Grupo_Usuario,
                    T.No_Dominio_Tienda_Virtual,
                    T.No_Subdominio_Tienda_Virtual,
                    T.Nu_Estado as TiendaEstado,
                    P.ID_Pais,
                    P.No_Pais,
                    MONE.*
                FROM
                    usuario AS USR
                    JOIN empresa AS EMP ON(EMP.ID_Empresa = USR.ID_Empresa)
                    JOIN pais AS P ON(P.ID_Pais = EMP.ID_Pais)
                    JOIN moneda AS MONE ON(EMP.ID_Empresa = MONE.ID_Empresa)
                    JOIN grupo_usuario AS GRPUSR ON(USR.ID_Usuario = GRPUSR.ID_Usuario)
                    JOIN grupo AS GRP ON(GRP.ID_Grupo = GRPUSR.ID_Grupo)
                    JOIN organizacion AS ORG ON(ORG.ID_Organizacion = USR.ID_Organizacion)
                    LEFT JOIN subdominio_tienda_virtual T ON T.ID_Empresa=USR.ID_Empresa
                WHERE
                    USR.No_Usuario = ?
                    AND GRP.ID_Empresa = ?
                    AND GRP.ID_Organizacion = ?
                    AND ORG.Nu_Estado = 1
                    AND USR.Nu_Estado=1 
                ORDER BY Fe_Creacion ASC LIMIT 1
            ", [$No_Usuario, $ID_Empresa, $ID_Organizacion]);

            if (empty($usuarioEspecifico)) {
                return [
                    'sStatus' => 'warning',
                    'sMessage' => 'Comunicarse con soporte para activación de cuenta'
                ];
            }

            return [
                'sStatus' => 'success',
                'sMessage' => 'Iniciando sesión'
            ];
        }
    }

    /**
     * Obtener menús del usuario
     *
     * @param \App\Models\Usuario $usuario
     * @return array
     */
    private function obtenerMenusUsuario($usuario)
    {
        try {
            $idGrupo = $usuario->ID_Grupo;
            $noUsuario = $usuario->No_Usuario;

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

            return $arrMenuPadre;
        } catch (\Exception $e) {
            // En caso de error, devolver array vacío
            return [];
        }
    }
}
