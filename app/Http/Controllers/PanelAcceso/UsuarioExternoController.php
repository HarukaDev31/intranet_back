<?php

namespace App\Http\Controllers\PanelAcceso;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UsuarioExternoController extends Controller
{
    /**
     * Listado de usuarios externos.
     * GET /api/panel-acceso/usuarios-externos
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('users AS USR')
                ->select(
                    'USR.id',
                    'USR.name',
                    'USR.lastname',
                    'USR.email',
                    'USR.whatsapp',
                    'USR.dni',
                    'USR.created_at'
                );

            if ($request->filled('search')) {
                $term = '%' . $request->search . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('USR.name',      'like', $term)
                      ->orWhere('USR.lastname', 'like', $term)
                      ->orWhere('USR.email',    'like', $term)
                      ->orWhere('USR.dni',      'like', $term);
                });
            }

            $usuarios = $query->orderBy('USR.id', 'desc')->get();

            $data = $usuarios->map(function ($u) {
                return [
                    'id'         => $u->id,
                    'name'       => $u->name,
                    'lastname'   => $u->lastname,
                    'nombre_completo' => trim(($u->name ?? '') . ' ' . ($u->lastname ?? '')),
                    'email'      => $u->email,
                    'whatsapp'   => $u->whatsapp,
                    'dni'        => $u->dni,
                    'created_at' => $u->created_at,
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('UsuarioExternoController@index: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al listar usuarios externos'], 500);
        }
    }

    /**
     * Obtener un usuario externo por ID.
     * GET /api/panel-acceso/usuarios-externos/{id}
     */
    public function show($id)
    {
        try {
            $usuario = DB::table('users')->where('id', $id)->first();

            if (!$usuario) {
                return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id'       => $usuario->id,
                    'name'     => $usuario->name,
                    'lastname' => $usuario->lastname,
                    'email'    => $usuario->email,
                    'whatsapp' => $usuario->whatsapp,
                    'dni'      => $usuario->dni,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('UsuarioExternoController@show: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener usuario'], 500);
        }
    }

    /**
     * Crear nuevo usuario externo.
     * POST /api/panel-acceso/usuarios-externos
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name'      => 'required|string|max:100',
                'lastname'  => 'nullable|string|max:100',
                'email'     => 'required|email|max:150|unique:users,email',
                'password'  => 'required|string|min:6',
                'whatsapp'  => 'nullable|string|max:20',
                'dni'       => 'nullable|string|max:20',
            ]);

            $idUsuario = DB::table('users')->insertGetId([
                'name'       => trim($request->name),
                'lastname'   => $request->lastname ? trim($request->lastname) : null,
                'email'      => trim($request->email),
                'password'   => Hash::make($request->password),
                'whatsapp'   => $request->whatsapp ? trim($request->whatsapp) : null,
                'dni'        => $request->dni ? trim($request->dni) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Usuario externo creado exitosamente', 'data' => ['id' => $idUsuario]]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('UsuarioExternoController@store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al crear usuario externo'], 500);
        }
    }

    /**
     * Actualizar usuario externo.
     * PUT /api/panel-acceso/usuarios-externos/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $usuario = DB::table('users')->where('id', $id)->first();
            if (!$usuario) {
                return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
            }

            $request->validate([
                'name'      => 'required|string|max:100',
                'lastname'  => 'nullable|string|max:100',
                'email'     => 'required|email|max:150',
                'password'  => 'nullable|string|min:6',
                'whatsapp'  => 'nullable|string|max:20',
                'dni'       => 'nullable|string|max:20',
            ]);

            // Validar email único excluyendo el actual
            $existeEmail = DB::selectOne(
                'SELECT COUNT(*) AS existe FROM users WHERE email = ? AND id != ? LIMIT 1',
                [trim($request->email), $id]
            );
            if ($existeEmail->existe > 0) {
                return response()->json(['success' => false, 'message' => 'El correo ya existe'], 422);
            }

            $dataUpdate = [
                'name'       => trim($request->name),
                'lastname'   => $request->lastname ? trim($request->lastname) : null,
                'email'      => trim($request->email),
                'whatsapp'   => $request->whatsapp ? trim($request->whatsapp) : null,
                'dni'        => $request->dni ? trim($request->dni) : null,
                'updated_at' => now(),
            ];

            if ($request->filled('password')) {
                $dataUpdate['password'] = Hash::make($request->password);
            }

            DB::table('users')->where('id', $id)->update($dataUpdate);

            return response()->json(['success' => true, 'message' => 'Usuario externo actualizado exitosamente']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('UsuarioExternoController@update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar usuario externo'], 500);
        }
    }

    /**
     * Eliminar usuario externo (cascade via FK a menu_user_access).
     * DELETE /api/panel-acceso/usuarios-externos/{id}
     */
    public function destroy($id)
    {
        try {
            $deleted = DB::table('users')->where('id', $id)->delete();

            if (!$deleted) {
                return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
            }

            return response()->json(['success' => true, 'message' => 'Usuario externo eliminado exitosamente']);
        } catch (\Exception $e) {
            Log::error('UsuarioExternoController@destroy: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al eliminar usuario externo'], 500);
        }
    }
}
