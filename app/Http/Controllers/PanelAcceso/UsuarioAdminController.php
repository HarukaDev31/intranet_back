<?php

namespace App\Http\Controllers\PanelAcceso;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Usuario;
use App\Models\GrupoUsuario;
use App\Helpers\CodeIgniterEncryption;

class UsuarioAdminController extends Controller
{
    /**
     * Listado de usuarios con filtros opcionales.
     * GET /api/panel-acceso/usuarios
     */
    public function index(Request $request)
    {
        try {
            $authUser = auth()->user();

            $query = DB::table('usuario AS USR')
                ->join('empresa AS EMP', 'EMP.ID_Empresa', '=', 'USR.ID_Empresa')
                ->join('organizacion AS ORG', 'ORG.ID_Organizacion', '=', 'USR.ID_Organizacion')
                ->leftJoin('grupo AS GRP', 'GRP.ID_Grupo', '=', 'USR.ID_Grupo')
                ->select(
                    'USR.ID_Usuario',
                    'USR.ID_Empresa',
                    'USR.ID_Organizacion',
                    'USR.ID_Grupo',
                    'USR.No_Usuario',
                    'USR.No_Nombres_Apellidos',
                    'USR.Txt_Email',
                    'USR.Nu_Celular',
                    'USR.Nu_Estado',
                    'EMP.No_Empresa',
                    'ORG.No_Organizacion',
                    'GRP.No_Grupo'
                );

            // El root (ID=1) ve todo; el resto no ve al root
            if ($authUser->ID_Usuario != 1) {
                $query->where('USR.ID_Usuario', '!=', 1);
            }

            if ($request->filled('empresa_id')) {
                $query->where('USR.ID_Empresa', $request->empresa_id);
            }

            if ($request->filled('org_id')) {
                $query->where('USR.ID_Organizacion', $request->org_id);
            }

            if ($request->filled('search')) {
                $term = $request->search;
                $query->where(function ($q) use ($term) {
                    $q->where('USR.No_Usuario', 'like', "%{$term}%")
                      ->orWhere('USR.No_Nombres_Apellidos', 'like', "%{$term}%");
                });
            }

            $usuarios = $query->orderBy('USR.ID_Usuario', 'desc')->get();

            $data = $usuarios->map(function ($u) {
                return [
                    'id'               => $u->ID_Usuario,
                    'id_empresa'       => $u->ID_Empresa,
                    'id_org'           => $u->ID_Organizacion,
                    'id_grupo'         => $u->ID_Grupo,
                    'empresa'          => $u->No_Empresa,
                    'organizacion'     => $u->No_Organizacion,
                    'cargo'            => $u->No_Grupo,
                    'usuario'          => $u->No_Usuario,
                    'nombres_apellidos'=> $u->No_Nombres_Apellidos,
                    'email'            => $u->Txt_Email,
                    'celular'          => $u->Nu_Celular,
                    'estado'           => $u->Nu_Estado,
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('UsuarioAdminController@index: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al listar usuarios'], 500);
        }
    }

    /**
     * Obtener un usuario por ID (sin password).
     * GET /api/panel-acceso/usuarios/{id}
     */
    public function show($id)
    {
        try {
            $usuario = DB::table('usuario AS USR')
                ->leftJoin('grupo AS GRP', 'GRP.ID_Grupo', '=', 'USR.ID_Grupo')
                ->select('USR.*', 'GRP.No_Grupo')
                ->where('USR.ID_Usuario', $id)
                ->first();

            if (!$usuario) {
                return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id'               => $usuario->ID_Usuario,
                    'id_empresa'       => $usuario->ID_Empresa,
                    'id_org'           => $usuario->ID_Organizacion,
                    'id_grupo'         => $usuario->ID_Grupo,
                    'cargo'            => $usuario->No_Grupo,
                    'usuario'          => $usuario->No_Usuario,
                    'nombres_apellidos'=> $usuario->No_Nombres_Apellidos,
                    'email'            => $usuario->Txt_Email,
                    'celular'          => $usuario->Nu_Celular,
                    'estado'           => $usuario->Nu_Estado,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('UsuarioAdminController@show: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener usuario'], 500);
        }
    }

    /**
     * Crear nuevo usuario + grupo_usuario.
     * POST /api/panel-acceso/usuarios
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'id_empresa'        => 'required|integer',
                'id_org'            => 'required|integer',
                'id_grupo'          => 'required|integer',
                'usuario'           => 'required|string|max:100',
                'nombres_apellidos' => 'nullable|string|max:100',
                'password'          => 'required|string',
                'celular'           => 'nullable|string|max:11',
                'estado'            => 'required|integer|in:0,1',
            ]);

            $email = trim($request->usuario);

            if ($email !== 'root' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['success' => false, 'message' => 'Debes ingresar un email válido'], 422);
            }

            // Validar duplicados
            $existeUsuario = DB::selectOne(
                'SELECT COUNT(*) AS existe FROM usuario WHERE ID_Organizacion = ? AND No_Usuario = ? LIMIT 1',
                [$request->id_org, $email]
            );
            if ($existeUsuario->existe > 0) {
                return response()->json(['success' => false, 'message' => 'El usuario ya existe'], 422);
            }

            $celular = $this->parseCelular($request->celular);

            if ($celular) {
                $existeCelular = DB::selectOne(
                    'SELECT COUNT(*) AS existe FROM usuario WHERE ID_Empresa = ? AND ID_Organizacion = ? AND Nu_Celular = ? LIMIT 1',
                    [$request->id_empresa, $request->id_org, $celular]
                );
                if ($existeCelular->existe > 0) {
                    return response()->json(['success' => false, 'message' => 'El número celular ya existe'], 422);
                }
            }

            $existeEmail = DB::selectOne(
                'SELECT COUNT(*) AS existe FROM usuario WHERE ID_Empresa = ? AND ID_Organizacion = ? AND Txt_Email = ? LIMIT 1',
                [$request->id_empresa, $request->id_org, $email]
            );
            if ($existeEmail->existe > 0) {
                return response()->json(['success' => false, 'message' => 'El correo ya existe'], 422);
            }

            $encryption = new CodeIgniterEncryption();
            $passwordEncriptado = $encryption->encrypt($request->password);

            DB::beginTransaction();

            $dataUsuario = [
                'ID_Empresa'            => $request->id_empresa,
                'ID_Organizacion'       => $request->id_org,
                'ID_Grupo'              => $request->id_grupo,
                'No_Usuario'            => $email,
                'No_Nombres_Apellidos'  => $request->nombres_apellidos,
                'No_Password'           => $passwordEncriptado,
                'Txt_Email'             => $email,
                'Txt_Token_Activacion'  => $encryption->encrypt($request->usuario),
                'No_IP'                 => request()->ip(),
                'Nu_Estado'             => $request->estado,
            ];

            if ($celular) {
                $dataUsuario['Nu_Celular'] = $celular;
            }

            $idUsuario = DB::table('usuario')->insertGetId($dataUsuario);

            DB::table('grupo_usuario')->insert([
                'ID_Usuario'      => $idUsuario,
                'ID_Grupo'        => $request->id_grupo,
                'ID_Empresa'      => $request->id_empresa,
                'ID_Organizacion' => $request->id_org,
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Usuario creado exitosamente', 'data' => ['id' => $idUsuario]]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('UsuarioAdminController@store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al crear usuario'], 500);
        }
    }

    /**
     * Actualizar usuario.
     * PUT /api/panel-acceso/usuarios/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'id_empresa'        => 'required|integer',
                'id_org'            => 'required|integer',
                'id_grupo'          => 'required|integer',
                'usuario'           => 'required|string|max:100',
                'nombres_apellidos' => 'nullable|string|max:100',
                'password'          => 'nullable|string',
                'celular'           => 'nullable|string|max:11',
                'estado'            => 'required|integer|in:0,1',
            ]);

            if ($id == 1) {
                $email = trim($request->usuario);
                if ($email !== 'root') {
                    return response()->json(['success' => false, 'message' => 'No se puede cambiar el nombre "root"'], 422);
                }
            }

            $usuario = DB::table('usuario')->where('ID_Usuario', $id)->first();
            if (!$usuario) {
                return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
            }

            $email = trim($request->usuario);
            $celular = $this->parseCelular($request->celular);

            // Validar duplicados solo si cambió algo
            if ($usuario->ID_Organizacion != $request->id_org || $usuario->No_Usuario != $email) {
                $existeUsuario = DB::selectOne(
                    'SELECT COUNT(*) AS existe FROM usuario WHERE ID_Organizacion = ? AND No_Usuario = ? AND ID_Usuario != ? LIMIT 1',
                    [$request->id_org, $email, $id]
                );
                if ($existeUsuario->existe > 0) {
                    return response()->json(['success' => false, 'message' => 'El usuario ya existe'], 422);
                }
            }

            if ($celular && $celular != $usuario->Nu_Celular) {
                $existeCelular = DB::selectOne(
                    'SELECT COUNT(*) AS existe FROM usuario WHERE ID_Empresa = ? AND ID_Organizacion = ? AND Nu_Celular = ? AND ID_Usuario != ? LIMIT 1',
                    [$request->id_empresa, $request->id_org, $celular, $id]
                );
                if ($existeCelular->existe > 0) {
                    return response()->json(['success' => false, 'message' => 'El número celular ya existe'], 422);
                }
            }

            if ($email != $usuario->Txt_Email) {
                $existeEmail = DB::selectOne(
                    'SELECT COUNT(*) AS existe FROM usuario WHERE ID_Empresa = ? AND ID_Organizacion = ? AND Txt_Email = ? AND ID_Usuario != ? LIMIT 1',
                    [$request->id_empresa, $request->id_org, $email, $id]
                );
                if ($existeEmail->existe > 0) {
                    return response()->json(['success' => false, 'message' => 'El correo ya existe'], 422);
                }
            }

            $dataUpdate = [
                'ID_Empresa'            => $request->id_empresa,
                'ID_Organizacion'       => $request->id_org,
                'ID_Grupo'              => $request->id_grupo,
                'No_Usuario'            => $email,
                'No_Nombres_Apellidos'  => $request->nombres_apellidos,
                'Txt_Email'             => $email,
                'Nu_Estado'             => $request->estado,
            ];

            if ($celular) {
                $dataUpdate['Nu_Celular'] = $celular;
            }

            if ($request->filled('password')) {
                $encryption = new CodeIgniterEncryption();
                $dataUpdate['No_Password'] = $encryption->encrypt($request->password);
            }

            DB::beginTransaction();

            DB::table('usuario')->where('ID_Usuario', $id)->update($dataUpdate);

            DB::table('grupo_usuario')->where('ID_Usuario', $id)->update([
                'ID_Grupo'        => $request->id_grupo,
                'ID_Empresa'      => $request->id_empresa,
                'ID_Organizacion' => $request->id_org,
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Usuario actualizado exitosamente']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('UsuarioAdminController@update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar usuario'], 500);
        }
    }

    /**
     * Eliminar usuario (cascade: menu_acceso → grupo_usuario → usuario).
     * DELETE /api/panel-acceso/usuarios/{id}
     */
    public function destroy($id)
    {
        try {
            if ($id == 1) {
                return response()->json(['success' => false, 'message' => 'No se puede eliminar el usuario ROOT'], 422);
            }

            $grupoUsuario = DB::table('grupo_usuario')->where('ID_Usuario', $id)->first();

            DB::beginTransaction();

            if ($grupoUsuario) {
                DB::table('menu_acceso')->where('ID_Grupo_Usuario', $grupoUsuario->ID_Grupo_Usuario)->delete();
                DB::table('grupo_usuario')->where('ID_Usuario', $id)->delete();
            }

            $deleted = DB::table('usuario')->where('ID_Usuario', $id)->delete();

            if (!$deleted) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Usuario eliminado exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('UsuarioAdminController@destroy: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al eliminar usuario'], 500);
        }
    }

    private function parseCelular(?string $celular): ?string
    {
        if (!$celular) return null;
        $clean = preg_replace('/[\s\-]/', '', $celular);
        return (strlen($clean) >= 9) ? $clean : null;
    }
}
