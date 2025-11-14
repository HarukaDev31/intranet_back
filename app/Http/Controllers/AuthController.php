<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\Usuario;
use App\Models\Empresa;
use App\Models\Organizacion;
use App\Models\Almacen;
use App\Models\Departamento;
use App\Models\Distrito;
use App\Models\Provincia;
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
        $this->middleware('auth:api', ['except' => ['login', 'register', 'loginCliente', 'meExternal', 'logoutExternal', 'refreshExternal', 'forgotPassword', 'resetPassword']]);
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

                        // Calcular CBM vendidos y embarcados (sin filtros de fecha en el login)
                        $idUsuario = $usuario->ID_Usuario;

                        // Calcular CBM vendidos en Perú (total de volumen confirmado por usuario)
                        $soldCBM = DB::table('contenedor_consolidado_cotizacion as cc')
                            ->leftJoin('contenedor_consolidado_cotizacion_proveedores as cccp', 'cc.id', '=', 'cccp.id_cotizacion')
                            ->leftJoin('carga_consolidada_contenedor as cont', 'cc.id_contenedor', '=', 'cont.id')
                            ->where('cc.estado_cotizador', 'CONFIRMADO')
                            ->where('cc.id_usuario', $idUsuario)
                            ->where('cont.empresa', '!=', '1')
                            ->whereNotNull('cc.fecha_confirmacion')
                            ->sum('cccp.cbm_total') ?? 0;

                        // Calcular CBM embarcados (total de cbm_total_china recibidos en china y embarcados)
                        $embarquedCBM = DB::table('contenedor_consolidado_cotizacion_proveedores as cccp')
                            ->join('contenedor_consolidado_cotizacion as cc', 'cccp.id_cotizacion', '=', 'cc.id')
                            ->join('carga_consolidada_contenedor as cont', 'cc.id_contenedor', '=', 'cont.id')
                            ->where('cccp.estados_proveedor', 'LOADED')
                            ->where('cc.id_usuario', $idUsuario)
                            ->where('cont.empresa', '!=', '1')
                            ->whereNull('cc.id_cliente_importacion')
                            ->where('cc.estado_cotizador', 'CONFIRMADO')
                            ->sum('cccp.cbm_total_china') ?? 0;

                        // Obtener datos del perfil (mismo formato que me())
                        $nombreCompleto = $usuario->No_Nombres_Apellidos ?? $usuario->No_Usuario ?? '';
                        $email = $usuario->Txt_Email ?? '';
                        
                        // Obtener fecha de nacimiento
                        $fechaNacimiento = '';
                        if ($usuario->Fe_Nacimiento) {
                            $fechaNacimiento = is_string($usuario->Fe_Nacimiento) 
                                ? $usuario->Fe_Nacimiento 
                                : (new \DateTime($usuario->Fe_Nacimiento))->format('Y-m-d');
                        }

                        // Obtener foto URL
                        $photoUrl = '';
                        if ($usuario->Txt_Foto) {
                            $photoUrl = $this->generateImageUrl($usuario->Txt_Foto);
                        }

                        $payload = [
                            'success' => true,
                            'message' => $result['sMessage'],
                            'token' => $token,
                            'token_type' => 'bearer',
                            'expires_in' => 24 * config('jwt.ttl') * 60,
                            'user' => [
                                'id' => $usuario->ID_Usuario,
                                'nombre' => $usuario->No_Usuario,
                                'nombres_apellidos' => $usuario->No_Nombres_Apellidos,
                                'fullName' => !empty($nombreCompleto) ? $nombreCompleto : null,
                                'photoUrl' => $photoUrl,
                                'email' => $email,
                                'dni' => $usuario->Nu_Documento ?? '',
                                'fechaNacimiento' => $fechaNacimiento,
                                'idCountry' => $usuario->ID_Pais ? (int)$usuario->ID_Pais : 0,
                                'idDepartment' => $usuario->ID_Departamento ? (int)$usuario->ID_Departamento : 0,
                                'idProvince' => $usuario->ID_Provincia ? (int)$usuario->ID_Provincia : 0,
                                'idDistrict' => $usuario->ID_Distrito ? (int)$usuario->ID_Distrito : 0,
                                'phone' => $usuario->Nu_Celular ?? null,
                                'soldCBM' => (float) $soldCBM,
                                'embarquedCBM' => (float) $embarquedCBM,
                                'goals' => $usuario->Txt_Objetivos ?? null,
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
                        ];

                        // Sanitize payload to avoid malformed UTF-8 characters during json_encode
                        $payload = $this->sanitizeForJson($payload);

                        return response()->json($payload);
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
                'message' => 'Error al iniciar sesión: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        try {
            $usuario = auth()->user();

            if (!$usuario) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            // El usuario autenticado es un Usuario (modelo Usuario), no User
            // Usamos ID_Usuario para los cálculos de CBM
            $idUsuario = $usuario->ID_Usuario;

            // Obtener fechas de filtro si vienen como query parameters
            $fechaInicio = $request->query('fecha_inicio');
            $fechaFin = $request->query('fecha_fin');

            // Calcular CBM vendidos en Perú (total de volumen confirmado por usuario)
            // Los CBM vendidos: son el total de todos los cbm vendidos en Peru
            // Usa la misma lógica que DashboardVentasController: suma cbm_total de cotizaciones confirmadas
            $soldCBMQuery = DB::table('contenedor_consolidado_cotizacion as cc')
                ->leftJoin('contenedor_consolidado_cotizacion_proveedores as cccp', 'cc.id', '=', 'cccp.id_cotizacion')
                ->leftJoin('carga_consolidada_contenedor as cont', 'cc.id_contenedor', '=', 'cont.id')
                ->where('cc.estado_cotizador', 'CONFIRMADO')
                ->where('cc.id_usuario', $idUsuario)
                ->where('cont.empresa', '!=', '1')
                ->whereNotNull('cc.fecha_confirmacion');

            // Aplicar filtro de fechas si vienen (usando fecha_confirmacion como en DashboardVentasController)
            if ($fechaInicio && $fechaFin) {
                $soldCBMQuery->whereBetween(DB::raw('DATE(cc.fecha_confirmacion)'), [$fechaInicio, $fechaFin]);
            }

            $soldCBM = $soldCBMQuery->sum('cccp.cbm_total') ?? 0;

            // Calcular CBM embarcados (total de cbm_total_china recibidos en china y embarcados)
            // Los cbm embarcados son el total de todos los cbm recibidos en china y embarcados
            // Usa fecha_zarpe del contenedor para filtrar cuando están embarcados
            $embarquedCBMQuery = DB::table('contenedor_consolidado_cotizacion_proveedores as cccp')
                ->join('contenedor_consolidado_cotizacion as cc', 'cccp.id_cotizacion', '=', 'cc.id')
                ->join('carga_consolidada_contenedor as cont', 'cc.id_contenedor', '=', 'cont.id')
                ->where('cccp.estados_proveedor', 'LOADED')
                ->where('cc.id_usuario', $idUsuario)
                ->where('cont.empresa', '!=', '1')
                ->whereNull('cc.id_cliente_importacion')
                ->where('cc.estado_cotizador', 'CONFIRMADO');

            // Aplicar filtro de fechas si vienen (usando fecha_zarpe para embarcados)
            if ($fechaInicio && $fechaFin) {
                $embarquedCBMQuery->whereBetween(DB::raw('DATE(cont.fecha_zarpe)'), [$fechaInicio, $fechaFin]);
            }

            $embarquedCBM = $embarquedCBMQuery->sum('cccp.cbm_total_china') ?? 0;

            // Obtener datos directamente del modelo Usuario
            $nombreCompleto = $usuario->No_Nombres_Apellidos ?? $usuario->No_Usuario ?? '';
            $email = $usuario->Txt_Email ?? '';
            
            // Obtener fecha de nacimiento
            $fechaNacimiento = '';
            if ($usuario->Fe_Nacimiento) {
                $fechaNacimiento = is_string($usuario->Fe_Nacimiento) 
                    ? $usuario->Fe_Nacimiento 
                    : (new \DateTime($usuario->Fe_Nacimiento))->format('Y-m-d');
            }

            // Obtener foto URL
            $photoUrl = '';
            if ($usuario->Txt_Foto) {
                $photoUrl = $this->generateImageUrl($usuario->Txt_Foto);
            }

            // Construir respuesta según el formato UserProfile usando directamente el modelo Usuario
            $userProfile = [
                'id' => $usuario->ID_Usuario,
                'fullName' => !empty($nombreCompleto) ? $nombreCompleto : null,
                'photoUrl' => $photoUrl,
                'email' => $email,
                'dni' => $usuario->Nu_Documento ?? '',
                'fechaNacimiento' => $fechaNacimiento,
                'idCountry' => $usuario->ID_Pais ? (int)$usuario->ID_Pais : 0,
                'idDepartment' => $usuario->ID_Departamento ? (int)$usuario->ID_Departamento : 0,
                'idProvince' => $usuario->ID_Provincia ? (int)$usuario->ID_Provincia : 0,
                'idDistrict' => $usuario->ID_Distrito ? (int)$usuario->ID_Distrito : 0,
                'phone' => $usuario->Nu_Celular ?? null,
                'soldCBM' => (float) $soldCBM,
                'embarquedCBM' => (float) $embarquedCBM,
                'goals' => $usuario->Txt_Objetivos ?? null,
            ];

            return response()->json([
                'success' => true,
                'user' => $userProfile
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener el usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar perfil del usuario
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request)
    {
        try {
            $usuario = auth()->user();

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Validar unicidad de email, dni y phone (excepto el usuario actual)
            $idUsuario = $usuario->ID_Usuario;
            
            if ($request->has('email')) {
                $email = $request->input('email');
                $emailExists = DB::table('usuario')
                    ->where('Txt_Email', $email)
                    ->where('ID_Usuario', '!=', $idUsuario)
                    ->exists();
                if ($emailExists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El correo electrónico ya está en uso'
                    ], 422);
                }
            }

            if ($request->has('dni')) {
                $dni = $request->input('dni');
                if ($dni) {
                    $dniExists = DB::table('usuario')
                        ->where('Nu_Documento', $dni)
                        ->where('ID_Usuario', '!=', $idUsuario)
                        ->exists();
                    if ($dniExists) {
                        return response()->json([
                            'success' => false,
                            'message' => 'El DNI ya está en uso'
                        ], 422);
                    }
                }
            }

            if ($request->has('phone')) {
                $phone = $request->input('phone');
                if ($phone) {
                    $phoneExists = DB::table('usuario')
                        ->where('Nu_Celular', $phone)
                        ->where('ID_Usuario', '!=', $idUsuario)
                        ->exists();
                    if ($phoneExists) {
                        return response()->json([
                            'success' => false,
                            'message' => 'El teléfono ya está en uso'
                        ], 422);
                    }
                }
            }

            DB::beginTransaction();

            // Preparar datos para actualizar (solo los que vienen en el request)
            $updateData = [];

            // Email
            if ($request->has('email')) {
                $updateData['Txt_Email'] = $request->input('email');
            }

            // Phone
            if ($request->has('phone')) {
                $updateData['Nu_Celular'] = $request->input('phone');
            }

            // DNI
            if ($request->has('dni')) {
                $updateData['Nu_Documento'] = $request->input('dni');
            }

            // Fecha de nacimiento
            if ($request->has('fecha_nacimiento')) {
                $updateData['Fe_Nacimiento'] = $request->input('fecha_nacimiento');
            }

            // Country
            if ($request->has('country')) {
                $updateData['ID_Pais'] = $request->input('country');
            }

            // Departamento
            if ($request->has('departamento')) {
                $updateData['ID_Departamento'] = $request->input('departamento');
            }

            // Provincia (puede venir como 'city' o 'province')
            if ($request->has('city')) {
                $updateData['ID_Provincia'] = $request->input('city');
            } elseif ($request->has('province')) {
                $updateData['ID_Provincia'] = $request->input('province');
            }

            // Distrito
            if ($request->has('distrito')) {
                $updateData['ID_Distrito'] = $request->input('distrito');
            }

            // Goals
            if ($request->has('goals')) {
                $updateData['Txt_Objetivos'] = $request->input('goals');
            }

            // Manejar foto si viene
            $photoPath = null;
            
            if ($request->hasFile('photo')) {
                // Eliminar foto anterior si existe
                $fotoAnterior = $usuario->Txt_Foto ?? null;
                if ($fotoAnterior && Storage::disk('public')->exists($fotoAnterior)) {
                    Storage::disk('public')->delete($fotoAnterior);
                }
                
                // Guardar nueva foto
                $photo = $request->file('photo');
                $photoName = 'profile_' . $idUsuario . '_' . time() . '.' . $photo->getClientOriginalExtension();
                $photoPath = $photo->storeAs('profiles', $photoName, 'public');
                $updateData['Txt_Foto'] = $photoPath;
            } elseif ($request->has('photo')) {
                // Manejar foto como base64/binary
                $photoData = $request->input('photo');
                
                if ($photoData && !empty($photoData)) {
                    // Eliminar foto anterior si existe
                    $fotoAnterior = $usuario->Txt_Foto ?? null;
                    if ($fotoAnterior && Storage::disk('public')->exists($fotoAnterior)) {
                        Storage::disk('public')->delete($fotoAnterior);
                    }
                    
                    // Decodificar base64 si es necesario
                    if (preg_match('/^data:image\/(\w+);base64,/', $photoData, $type)) {
                        $photoData = substr($photoData, strpos($photoData, ',') + 1);
                        $type = strtolower($type[1]);
                    } else {
                        $type = 'jpg'; // Default
                    }
                    
                    $photoDecoded = base64_decode($photoData);
                    if ($photoDecoded !== false) {
                        $photoName = 'profile_' . $idUsuario . '_' . time() . '.' . $type;
                        $photoPath = 'profiles/' . $photoName;
                        Storage::disk('public')->put($photoPath, $photoDecoded);
                        $updateData['Txt_Foto'] = $photoPath;
                    }
                }
            }

            // Actualizar directamente en la tabla usuario
            if (!empty($updateData)) {
                DB::table('usuario')
                    ->where('ID_Usuario', $idUsuario)
                    ->update($updateData);
                
                // Refrescar el modelo
                $usuario = Usuario::find($idUsuario);
            }

            DB::commit();

            // Obtener fecha de nacimiento formateada
            $fechaNacimiento = '';
            if ($usuario->Fe_Nacimiento) {
                $fechaNacimiento = is_string($usuario->Fe_Nacimiento) 
                    ? $usuario->Fe_Nacimiento 
                    : (new \DateTime($usuario->Fe_Nacimiento))->format('Y-m-d');
            }

            // Obtener foto URL
            $photoUrl = '';
            if ($usuario->Txt_Foto) {
                $photoUrl = $this->generateImageUrl($usuario->Txt_Foto);
            } elseif ($photoPath) {
                $photoUrl = $this->generateImageUrl($photoPath);
            }

            // Construir respuesta según el formato UserProfile
            $response = [
                'success' => true,
                'message' => 'Perfil actualizado exitosamente',
                'user' => [
                    'id' => $usuario->ID_Usuario,
                    'fullName' => !empty($usuario->No_Nombres_Apellidos) ? $usuario->No_Nombres_Apellidos : ($usuario->No_Usuario ?? null),
                    'photoUrl' => $photoUrl,
                    'email' => $usuario->Txt_Email ?? '',
                    'dni' => $usuario->Nu_Documento ?? '',
                    'fechaNacimiento' => $fechaNacimiento,
                    'idCountry' => $usuario->ID_Pais ? (int)$usuario->ID_Pais : 0,
                    'idDepartment' => $usuario->ID_Departamento ? (int)$usuario->ID_Departamento : 0,
                    'idProvince' => $usuario->ID_Provincia ? (int)$usuario->ID_Provincia : 0,
                    'idDistrict' => $usuario->ID_Distrito ? (int)$usuario->ID_Distrito : 0,
                    'phone' => $usuario->Nu_Celular ?? null,
                    'soldCBM' => 0, // Se calcula en me(), no en profile
                    'embarquedCBM' => 0, // Se calcula en me(), no en profile
                    'goals' => $usuario->Txt_Objetivos ?? null,
                ]
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en profile AuthController: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el perfil: ' . $e->getMessage()
            ], 500);
        }
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
     * Recursively sanitize data to ensure strings are valid UTF-8 before json_encode.
     * Attempts several common conversions and falls back to removing invalid bytes.
     *
     * @param mixed $data
     * @return mixed
     */
    private function sanitizeForJson($data)
    {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $out[$k] = $this->sanitizeForJson($v);
            }
            return $out;
        }

        if (is_object($data)) {
            foreach ($data as $k => $v) {
                $data->$k = $this->sanitizeForJson($v);
            }
            return $data;
        }

        if (is_string($data)) {
            // If already valid UTF-8, return as-is
            if (function_exists('mb_check_encoding')) {
                if (mb_check_encoding($data, 'UTF-8')) return $data;
            } else {
                // try a quick iconv check
                $try = @iconv('UTF-8', 'UTF-8//IGNORE', $data);
                if ($try !== false && $try === $data) return $data;
            }

            // Try common conversions
            $conversions = [
                ['from' => 'ISO-8859-1', 'to' => 'UTF-8//TRANSLIT'],
                ['from' => 'CP1252', 'to' => 'UTF-8//TRANSLIT'],
                ['from' => 'UTF-8', 'to' => 'UTF-8//IGNORE'],
            ];

            foreach ($conversions as $c) {
                $converted = @iconv($c['from'], $c['to'], $data);
                if ($converted !== false) {
                    if (!function_exists('mb_check_encoding') || mb_check_encoding($converted, 'UTF-8')) {
                        return $converted;
                    }
                }
            }

            // Last resorts
            $converted = @utf8_encode($data);
            if ($converted !== false) return $converted;

            // Remove bytes that are not valid UTF-8 as final fallback
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $data);
            if ($clean !== false) return $clean;

            return $data;
        }

        return $data;
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
        Log::info('validated data', $validatedData);

        try {
            $user = User::create([
                'name' => $validatedData['nombre'],
                'lastname' => $validatedData['lastname'] ?? null,
                'email' => $validatedData['email'],
                'whatsapp' => $validatedData['whatsapp'] ?? null,
                'goals' => $validatedData['goals'] ?? null,
                'password' => Hash::make($validatedData['password']),
                'dni' => $validatedData['dni'] ?? null,
                'birth_date' => $validatedData['fechaNacimiento'] ?? null,
                'provincia_id' => $validatedData['provincia_id'] ?? null,
                'departamento_id' => $validatedData['departamento_id'] ?? null,
                'distrito_id' => $validatedData['distrito_id'] ?? null,
            ]);

            Log::info('user created', $user->toArray());

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
                        public_path('storage/logo_icons/logo_header_white.png'),
                        public_path('storage/logo_icons/logo_footer_white.png'),
                        public_path('storage/social_icons/facebook.png'),
                        public_path('storage/social_icons/instagram.png'),
                        public_path('storage/social_icons/tiktok.png'),
                        public_path('storage/social_icons/youtube.png')
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
                    'name' => $user->full_name,
                    'photoUrl' => $this->generateImageUrl($user->photo_url),
                    'email' => $user->email,
                    'dni' => $user->dni, // Campo no disponible en la estructura actual
                    'fechaNacimiento' => $user->birth_date, // Campo no disponible en la estructura actual
                    'country' => $user->pais_id, // Campo no disponible en la estructura actual
                    'city' => $user->provincia ? $user->provincia->No_Provincia : null,
                    'department' => $user->departamento ? $user->departamento->No_Departamento : null,
                    'province' => $user->provincia ? $user->provincia->No_Provincia : null,
                    'district' => $user->distrito ? $user->distrito->No_Distrito : null,
                    'phone' => $user->whatsapp,
                    'empresa' => $user->userBusiness, // No hay negocio asociado al registrarse
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
                        'name' => $user->full_name,
                        'photoUrl' => $this->generateImageUrl($user->photo_url),
                        'email' => $user->email,
                        'dni' => $user->dni, // Campo no disponible en la estructura actual
                        'fechaNacimiento' => $user->birth_date, // Campo no disponible en la estructura actual
                        'country' => $user->pais_id, // Campo no disponible en la estructura actual
                        'city' => $user->provincia_id, // Campo no disponible en la estructura actual
                        'department' => $user->departamento_id, // Campo no disponible en la estructura actual
                        'district' => $user->distrito_id, // Campo no disponible en la
                        'phone' => $user->whatsapp,
                        'business' => $business,
                        'importedAmount' => 0, // Campo no disponible en la estructura actual
                        'importedContainers' => 0, // Campo no disponible en la estructura actual
                        'goals' => $user->goals,
                        'empresa' => $user->userBusiness,
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
                'message' => 'Error al iniciar sesión: ' . $e->getMessage()
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

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ], 401);
            }

            // Obtener menús del usuario externo
            $menus = $this->obtenerMenusUsuarioExterno($user);

            // Cargar la relación con userBusiness y relaciones de ubicación, incluyendo pais
            $user->load(['userBusiness', 'departamento', 'distrito', 'provincia', 'pais']);

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
            $importedAmount = $this->getUserCotizacionesByWhatsapp($user->whatsapp, $user->dni);

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'fullName' => $user->full_name,
                    'photoUrl' => $this->generateImageUrl($user->photo_url),
                    'email' => $user->email,
                    'country' => $user->pais_id ? $user->pais->No_Pais : null, // Campo no disponible en la estructura actual
                    'birth_date' => $user->birth_date,
                    'id_country' => $user->pais_id,
                    'city' => $user->provincia_id ? $user->provincia->No_Provincia : null,
                    'department' => $user->departamento_id ? $user->departamento->No_Departamento : null,
                    'id_department' => $user->departamento_id,
                    'province' => $user->provincia ? $user->provincia->No_Provincia : null,
                    'id_province' => $user->provincia_id,
                    'district' => $user->distrito ? $user->distrito->No_Distrito : null,
                    'id_district' => $user->distrito_id,
                    'phone' => $user->whatsapp,
                    'business' => $business,
                    'importedAmount' => $importedAmount['sumFob'] + $importedAmount['sumImpuestos'] + $importedAmount['sumLogistica'], // Campo no disponible en la estructura actual
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
                    'cbm' => $importedAmount['cbm'] ?? 0
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
    public function getUserCotizacionesByWhatsapp($whatsapp, $dni = null)
    {
        try {
            // Limpiar whatsapp para búsqueda (remover espacios, guiones, etc)
            $cleanWhatsapp = preg_replace('/[\s\-\(\)\.\+]/', '', trim($whatsapp));
            //if lenght is 9 remove 51 to cleanWhatsapp
            if (strlen($cleanWhatsapp) == 9) {
                $cleanWhatsapp = preg_replace('/^51/', '', $cleanWhatsapp);
            }
            // Obtener correo del usuario si está disponible
            $correo = null;
            if ($dni) {
                $user = User::where('dni', $dni)->first();
                $correo = $user ? $user->email : null;
            }

            $trayectos = Cotizacion::where('estado_cotizador', 'CONFIRMADO')
                ->whereNull('id_cliente_importacion')
                ->whereNotNull('estado_cliente')
                ->where(function ($query) use ($cleanWhatsapp, $dni, $correo) {
                    // Usar la misma validación del modelo Cliente (getServiciosAttribute)
                    // Validar que el teléfono no sea nulo o vacío antes de procesar
                    if (!empty($cleanWhatsapp) && $cleanWhatsapp !== null) {
                        $query->where(DB::raw('REPLACE(TRIM(telefono), " ", "")'), 'LIKE', "%{$cleanWhatsapp}%");
                    }

                    // Validar que el documento no sea nulo o vacío antes de procesar
                    if (!empty($dni) && $dni !== null) {
                        $query->orWhere(function ($q) use ($dni) {
                            $q->whereNotNull('documento')
                                ->where('documento', '!=', '')
                                ->where('documento', $dni);
                        });
                    }

                    // Validar que el correo no sea nulo o vacío antes de procesar
                    if (!empty($correo) && $correo !== null) {
                        $query->orWhere(function ($q) use ($correo) {
                            $q->whereNotNull('correo')
                                ->where('correo', '!=', '')
                                ->where('correo', $correo);
                        });
                    }
                })
                ->whereHas('proveedores')

                ->select('id', 'fob_final', 'volumen', 'fob', 'monto', 'id_contenedor', 'impuestos_final', 'impuestos', 'logistica_final', 'volumen_doc', 'volumen_final')
                ->get();

            Log::info('Trayectos encontrados: ' . $trayectos->count());

            // Calcular la suma de FOB
            $sumFob = $trayectos->sum(function ($cotizacion) {
                return (float)(($cotizacion->fob_final == 0 || $cotizacion->fob_final == null) ? $cotizacion->fob : $cotizacion->fob_final);
            });

            $sumImpuestos = $trayectos->sum(function ($cotizacion) {
                return (float)(($cotizacion->impuestos_final == 0 || $cotizacion->impuestos_final == null) ? $cotizacion->impuestos : $cotizacion->impuestos_final);
            });

            $sumLogistica = $trayectos->sum(function ($cotizacion) {
                return (float)(($cotizacion->logistica_final == 0 || $cotizacion->logistica_final == null) ? $cotizacion->monto : $cotizacion->logistica_final);
            });

            //Calcular la suma cbm
            $volumen_final = $trayectos->sum(function ($cotizacion) {
                return (float)(($cotizacion->volumen_final == 0 || $cotizacion->volumen_final == null) ? $cotizacion->volumen : $cotizacion->volumen_final);
            });

            // Contar contenedores totales aun no sean unicos
            $containerCount = $trayectos->count();
            return [
                'success' => true,
                'sumFob' => $sumFob,
                'sumImpuestos' => $sumImpuestos,
                'sumLogistica' => $sumLogistica,
                'count' => $containerCount,
                'cbm' => $volumen_final
            ];
        } catch (\Exception $e) {
            Log::error('Error en getUserCotizacionesByWhatsapp: ' . $e->getMessage());
            return [
                'success' => false,
                'sumFob' => 0,
                'sumImpuestos' => 0,
                'sumLogistica' => 0,
                'count' => 0,
                'message' => 'Error al obtener cotizaciones',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generar token de recuperación de contraseña y enviar correo
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email inválido',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = $request->email;

            // Buscar usuario por email en la tabla users (clientes)
            $user = User::where('email', $email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró ningún usuario con ese correo electrónico'
                ], 404);
            }

            // Generar token único
            $token = Str::random(64);

            // Guardar o actualizar en password_resets
            DB::table('password_resets')->updateOrInsert(
                ['email' => $email],
                [
                    'email' => $email,
                    'token' => Hash::make($token),
                    'created_at' => now()
                ]
            );

            // Construir URL de reset (viene del frontend)
            $frontendUrl = env('APP_URL_CLIENTES', 'http://localhost:3001');
            $resetUrl = $frontendUrl . '/reset-password?token=' . $token;

            // Despachar job para enviar email
            \App\Jobs\SendForgotPasswordEmailJob::dispatch($email, $token, $resetUrl)->onQueue('emails');

            Log::info('Token de recuperación de contraseña generado y correo enviado', [
                'email' => $email,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Se ha enviado un correo con las instrucciones para recuperar tu contraseña'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en forgotPassword: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud de recuperación de contraseña'
            ], 500);
        }
    }

    /**
     * Restablecer contraseña con token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
                'password_confirmation' => 'required|string'
            ], [
                'password.min' => 'La contraseña debe tener al menos 8 caracteres',
                'password.confirmed' => 'Las contraseñas no coinciden',
                'token.required' => 'El token es requerido',
                'password.required' => 'La contraseña es requerida',
                'password_confirmation.required' => 'La confirmación de contraseña es requerida'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $token = $request->token;
            $password = $request->password;

            // Obtener todos los registros de password_resets
            $passwordResets = DB::table('password_resets')->get();

            // Buscar el registro correcto comparando el token hasheado
            $resetRecord = null;
            foreach ($passwordResets as $record) {
                if (Hash::check($token, $record->token)) {
                    $resetRecord = $record;
                    break;
                }
            }

            if (!$resetRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'El token de recuperación es inválido o ha expirado'
                ], 404);
            }

            // Verificar que el token no haya expirado (60 minutos)
            $createdAt = \Carbon\Carbon::parse($resetRecord->created_at);
            if ($createdAt->addMinutes(60)->isPast()) {
                // Eliminar token expirado
                DB::table('password_resets')
                    ->where('email', $resetRecord->email)
                    ->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'El token de recuperación ha expirado. Por favor, solicita uno nuevo.'
                ], 410);
            }

            // Buscar usuario por email
            $user = User::where('email', $resetRecord->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Actualizar contraseña
            $user->password = Hash::make($password);
            $user->save();

            // Eliminar el token usado
            DB::table('password_resets')
                ->where('email', $resetRecord->email)
                ->delete();

            Log::info('Contraseña restablecida exitosamente', [
                'email' => $user->email,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => '¡Contraseña restablecida exitosamente! Ahora puedes iniciar sesión con tu nueva contraseña.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en resetPassword: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al restablecer la contraseña'
            ], 500);
        }
    }
}
