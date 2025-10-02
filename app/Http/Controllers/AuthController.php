<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\Usuario;
use App\Models\Empresa;
use App\Models\Organizacion;
use App\Models\Almacen;
use App\Helpers\CodeIgniterEncryption;
use App\Http\Controllers\MenuController;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Models\CargaConsolidada\Cotizacion;
use App\Traits\FileTrait;
class AuthController extends Controller
{
    use FileTrait;
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register', 'loginCliente', 'meExternal', 'logoutExternal', 'refreshExternal']]);
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
                            'success' => true,
                            'message' => $result['sMessage'],
                            'token' => $token,
                            'token_type' => 'bearer',
                            'expires_in' => 24 * config('jwt.ttl') * 60,
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
            'success' => true,
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
            //orde by Nu_Orden
            $arrMenuPadre = collect($arrMenuPadre)->sortBy('Nu_Orden')->toArray();
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

    /**
     * Obtener menús del usuario externo
     *
     * @param \App\Models\User $user
     * @return array
     */
    private function obtenerMenusUsuarioExterno($user)
    {
        try {
            $userId = $user->id;

            // Obtener menús padre para usuarios externos
            $arrMenuPadre = DB::select("
                SELECT DISTINCT
                    MNU.*,
                    (SELECT COUNT(*) FROM menu_user WHERE ID_Padre = MNU.ID_Menu AND Nu_Activo = 0) AS Nu_Cantidad_Menu_Padre
                FROM menu_user AS MNU
                JOIN menu_user_access AS MNUACCESS ON (MNU.ID_Menu = MNUACCESS.ID_Menu)
                WHERE MNU.ID_Padre = 0
                AND MNU.Nu_Activo = 0
                AND MNUACCESS.user_id = ?
                ORDER BY MNU.Nu_Orden, MNU.ID_Menu ASC
            ", [$userId]);

            // Convertir a array y ordenar por Nu_Orden
            $arrMenuPadre = collect($arrMenuPadre)->sortBy('Nu_Orden')->values()->toArray();

            // Obtener hijos para cada menú padre
            foreach ($arrMenuPadre as $rowPadre) {
                $sqlHijos = "
                    SELECT DISTINCT
                        MNU.*,
                        (SELECT COUNT(*) FROM menu_user WHERE ID_Padre = MNU.ID_Menu AND Nu_Activo = 0) AS Nu_Cantidad_Menu_Hijos
                    FROM menu_user AS MNU
                    JOIN menu_user_access AS MNUACCESS ON (MNU.ID_Menu = MNUACCESS.ID_Menu)
                    WHERE MNU.ID_Padre = ?
                    AND MNU.Nu_Activo = 0
                    AND MNUACCESS.user_id = ?
                    ORDER BY MNU.Nu_Orden
                ";

                $rowPadre->Hijos = DB::select($sqlHijos, [$rowPadre->ID_Menu, $userId]);

                // Obtener sub-hijos para cada hijo
                foreach ($rowPadre->Hijos as $rowSubHijos) {
                    if ($rowSubHijos->Nu_Cantidad_Menu_Hijos > 0) {
                        $sqlSubHijos = "
                            SELECT DISTINCT MNU.*
                            FROM menu_user AS MNU
                            JOIN menu_user_access AS MNUACCESS ON (MNU.ID_Menu = MNUACCESS.ID_Menu)
                            WHERE MNU.ID_Padre = ?
                            AND MNU.Nu_Activo = 0
                            AND MNUACCESS.user_id = ?
                            ORDER BY MNU.Nu_Orden
                        ";

                        $rowSubHijos->SubHijos = DB::select($sqlSubHijos, [$rowSubHijos->ID_Menu, $userId]);
                    } else {
                        $rowSubHijos->SubHijos = [];
                    }
                }
            }

            return $arrMenuPadre;
        } catch (\Exception $e) {
            Log::error('Error obteniendo menús de usuario externo: ' . $e->getMessage());
            // En caso de error, devolver array vacío
            return [];
        }
    }

    /**
     * Asignar todos los menús disponibles a un usuario
     *
     * @param int $userId
     * @return void
     */
    private function asignarMenusUsuario($userId)
    {
        try {
            // Obtener todos los menús disponibles en menu_user
            $menus = DB::table('menu_user')->select('ID_Menu')->get();

            // Preparar datos para inserción masiva
            $menuAccess = [];
            foreach ($menus as $menu) {
                $menuAccess[] = [
                    'ID_Menu' => $menu->ID_Menu,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            // Insertar todos los accesos de menú para el usuario
            if (!empty($menuAccess)) {
                DB::table('menu_user_access')->insert($menuAccess);
                Log::info("Asignados " . count($menuAccess) . " menús al usuario ID: " . $userId);
            }
        } catch (\Exception $e) {
            Log::error('Error asignando menús al usuario ' . $userId . ': ' . $e->getMessage());
        }
    }

    public function register(RegisterRequest $request)
    {
        DB::beginTransaction();
        Log::info('register', $request->all());
        $validatedData = $request->validated();
        try {
            $user = User::create([
                'name' => $validatedData['nombre'],
                'lastname' => $validatedData['lastname'] ?? null,
                'email' => $validatedData['email'],
                'whatsapp' => $validatedData['whatsapp'] ?? null,
                'goals' => $validatedData['goals'] ?? null,
                'password' => Hash::make($validatedData['password']),
                'dni' => $validatedData['dni'] ?? null,
            ]);

            $token = JWTAuth::fromUser($user);

            $user->api_token = $token;
            $user->save();

            // Asignar todos los menús disponibles al usuario recién registrado
            $this->asignarMenusUsuario($user->id);

            // Obtener menús del usuario externo
            $menus = $this->obtenerMenusUsuarioExterno($user);

            try {
                Mail::to($user['email'])->send(
                    new \App\Mail\RegisterConfirmationMail(
                        $user['email'],
                        $validatedData['password'],
                        $user['nombre'],
                        public_path('storage/logo_header.png'),
                        public_path('storage/logo_footer.png')
                    )
                );
            } catch (\Exception $mailException) {
                Log::warning('Error enviando email de confirmación: ' . $mailException->getMessage());
                // No fallar el registro por problemas de email
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'success' => true,
                'message' => 'Usuario registrado correctamente',
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => 24 * config('jwt.ttl') * 60,
                'user' => [
                    'id' => $user->id,
                    'fullName' => $user->full_name,
                    'photoUrl' => $this->generateImageUrl($user->photo_url),
                    'email' => $user->email,
                    'documentNumber' => null, // Campo no disponible en la estructura actual
                    'age' => null, // Campo no disponible en la estructura actual
                    'country' => null, // Campo no disponible en la estructura actual
                    'city' => null, // Campo no disponible en la estructura actual
                    'phone' => $user->whatsapp,
                    'business' => null, // No hay negocio asociado al registrarse
                    'importedAmount' => 0, // Campo no disponible en la estructura actual
                    'importedContainers' => 0, // Campo no disponible en la estructura actual
                    'goals' => $user->goals,
                    'raw' => [
                        'grupo' => [
                            'id' => 1,
                            'nombre' => "Cliente",
                            'descripcion' => "Cliente",
                            'tipo_privilegio' => 1,
                            'estado' => 1,
                            'notificacion' => 1
                        ],
                    ],
                ],
                'iCantidadAcessoUsuario' => 1,
                
                'iIdEmpresa' => null,
                'menus' => $menus,
                'success' => true
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage(), 'success' => false], 500);
        }
    }
    public function loginCliente(Request $request)
    {
        try {
            $credentials = $request->only(['No_Usuario', 'No_Password']);

            // Validar campos requeridos
            if (empty($credentials['No_Usuario']) || empty($credentials['No_Password'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email y contraseña son requeridos'
                ], 400);
            }

            // Buscar usuario por email
            $user = User::where('email', $credentials['No_Usuario'])->first();

            if (!$user) {
                return response()->json([
                    'status' => 'danger',
                    'message' => 'Usuario no encontrado'
                ], 401);
            }

            // Verificar contraseña
            if (!Hash::check($credentials['No_Password'], $user->password)) {
                return response()->json([
                    'status' => 'warning',
                    'message' => 'Contraseña incorrecta'
                ], 401);
            }

            try {
                // Generar token JWT para usuarios externos
                config(['auth.defaults.guard' => 'api-external']);
                $token = JWTAuth::fromUser($user);

                // Obtener menús del usuario externo
                $menus = $this->obtenerMenusUsuarioExterno($user);

                // Cargar la relación con userBusiness
                $user->load('userBusiness');

                // Preparar información del negocio
                $business = null;
                if ($user->userBusiness) {
                    $business = [
                        'id' => $user->userBusiness->id,
                        'name' => $user->userBusiness->name,
                        'ruc' => $user->userBusiness->ruc,
                        'comercialCapacity' => $user->userBusiness->comercial_capacity,
                        'rubric' => $user->userBusiness->rubric,
                        'socialAddress' => $user->userBusiness->social_address,
                    ];
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Iniciando sesión',
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => 24 * config('jwt.ttl') * 60,
                    'user' => [
                        'id' => $user->id,
                        'fullName' => $user->full_name,
                        'photoUrl' => $this->generateImageUrl($user->photo_url),
                        'email' => $user->email,
                        'documentNumber' => null, // Campo no disponible en la estructura actual
                        'age' => null, // Campo no disponible en la estructura actual
                        'country' => null, // Campo no disponible en la estructura actual
                        'city' => null, // Campo no disponible en la estructura actual
                        'phone' => $user->whatsapp,
                        'business' => $business,
                        'importedAmount' => 0, // Campo no disponible en la estructura actual
                        'importedContainers' => 0, // Campo no disponible en la estructura actual
                        'goals' => $user->goals,
                        'dni' => $user->dni,
                        'raw' => [
                            'grupo' => [
                                'id' => 1,
                                'nombre' => "Cliente",
                                'descripcion' => "Cliente",
                                'tipo_privilegio' => 1,
                                'estado' => 1,
                                'notificacion' => 1
                            ],
                        ],
                    ],

                    'iCantidadAcessoUsuario' => 1,
                    'iIdEmpresa' => null,
                    'menus' => $menus,
                    'success' => true
                ]);
            } catch (JWTException $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudo crear el token'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en loginCliente: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al iniciar sesión'
            ], 500);
        }
    }

    /**
     * Get the authenticated external user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function meExternal()
    {
        try {
            $user = JWTAuth::user();
            /* return this format export interface UserProfile{
    id:number,
    fullName:string,
    photoUrl:string,
    email:string,
    documentNumber:string,
    age:number,
    country:string,
    city?:string,
    phone?:string,
    business?:UserBusiness,   
    importedAmount:number,
    importedContainers:number,
    goals?:string, 
}
export interface UserBusiness{
    id:number,
    name:string,
    ruc:string,
    comercialCapacity:string,
    rubric:string,
    socialAddress?:string,
}*/

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ], 401);
            }

            // Obtener menús del usuario externo
            $menus = $this->obtenerMenusUsuarioExterno($user);

            // Cargar la relación con userBusiness
            $user->load('userBusiness');

            // Preparar información del negocio
            $business = null;
            if ($user->userBusiness) {
                $business = [
                    'id' => $user->userBusiness->id,
                    'name' => $user->userBusiness->name,
                    'ruc' => $user->userBusiness->ruc,
                    'comercialCapacity' => $user->userBusiness->comercial_capacity,
                    'rubric' => $user->userBusiness->rubric,
                    'socialAddress' => $user->userBusiness->social_address,
                ];
            }
            $importedAmount = $this->getUserCotizacionesByWhatsapp($user->whatsapp);

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'fullName' => $user->full_name,
                    'photoUrl' => $this->generateImageUrl($user->photo_url),
                    'email' => $user->email,
                    'documentNumber' => null, // Campo no disponible en la estructura actual
                    'age' => null, // Campo no disponible en la estructura actual
                    'country' => null, // Campo no disponible en la estructura actual
                    'city' => null, // Campo no disponible en la estructura actual
                    'phone' => $user->whatsapp,
                    'business' => $business,
                    'importedAmount' => $importedAmount['sumFob'], // Campo no disponible en la estructura actual
                    'importedContainers' => $importedAmount['count'], // Campo no disponible en la estructura actual
                    'goals' => $user->goals,
                    'dni' => $user->dni,
                    'raw' => [
                        'grupo' => [
                            'id' => 1,
                            'nombre' => "Cliente",
                            'descripcion' => "Cliente",
                            'tipo_privilegio' => 1,
                            'estado' => 1,
                            'notificacion' => 1
                        ],
                    ],
                ],
                'iCantidadAcessoUsuario' => 1,
                'iIdEmpresa' => null,
                'menus' => $menus
            ]);
        } catch (\Exception $e) {
            Log::error('Error en meExternal: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener información del usuario'
            ], 500);
        }
    }

    /**
     * Log the external user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logoutExternal()
    {
        try {
            config(['auth.defaults.guard' => 'api-external']);
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json([
                'success' => true,
                'message' => 'Usuario desconectado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error en logoutExternal: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'message' => 'Usuario desconectado exitosamente'
            ]);
        }
    }

    /**
     * Refresh external user token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshExternal()
    {
        try {
            config(['auth.defaults.guard' => 'api-external']);
            $token = JWTAuth::refresh();
            return response()->json([
                'success' => true,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => 24 * config('jwt.ttl') * 60
            ]);
        } catch (\Exception $e) {
            Log::error('Error en refreshExternal: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al refrescar token'
            ], 500);
        }
    }
    public function getUserCotizacionesByWhatsapp($whatsapp)
    {
        try {
            // Limpiar el número de WhatsApp para la búsqueda
            $cleanWhatsapp = trim($whatsapp);
            $cleanWhatsapp = str_replace('+51', '', $cleanWhatsapp); // Remover código de país Perú
            $cleanWhatsapp = preg_replace('/[^0-9]/', '', $cleanWhatsapp); // Solo números
            
            $trayectos = Cotizacion::where('estado_cotizador', 'CONFIRMADO')
                ->whereNull('id_cliente_importacion')
                ->whereNotNull('estado_cliente')
                ->where(function($query) use ($cleanWhatsapp) {
                    $query->where(DB::raw('TRIM(REPLACE(telefono, "+51", ""))'), 'like', '%' . $cleanWhatsapp . '%')
                          ->orWhere(DB::raw('TRIM(telefono)'), 'like', '%' . $cleanWhatsapp . '%');
                })
                ->select('id', 'fob_final', 'fob', 'monto', 'id_contenedor')
                ->get();
            Log::info('Trayectos: ' . $trayectos);
            // Calcular la suma de FOB
            $sumFob = $trayectos->sum(function($cotizacion) {
                return (float)($cotizacion->fob_final ?? $cotizacion->fob ?? 0);
            });
            //get trayectos with diferente id_contenedor
            $containerCount = $trayectos->unique('id_contenedor')->count();

            return [
                'success' => true,
                'sumFob' => $sumFob,
                'count' => $containerCount
            ];
        } catch (\Exception $e) {
            Log::error('Error en getUserCotizacionesByWhatsapp: ' . $e->getMessage());
            return [
                'success' => false,
                'sumFob' => 0,
                'count' => 0,
                'message' => 'Error al obtener cotizaciones',
                'error' => $e->getMessage()
            ];
        }
    }
}
