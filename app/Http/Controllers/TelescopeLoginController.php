<?php

namespace App\Http\Controllers;

use App\Helpers\CodeIgniterEncryption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class TelescopeLoginController extends Controller
{
    /**
     * Formulario de login para Telescope (usuarios internos).
     */
    public function showLoginForm()
    {
        if (Session::has('telescope_authenticated')) {
            return redirect('/' . trim((string) config('telescope.path', 'telescope'), '/'));
        }

        return view('telescope.login');
    }

    /**
     * Valida credenciales de tabla usuario y deja sesión para Telescope.
     */
    public function login(Request $request)
    {
        $request->validate([
            'No_Usuario' => 'required|string',
            'No_Password' => 'required|string',
        ]);

        $No_Usuario = trim($request->input('No_Usuario'));
        $No_Password = trim($request->input('No_Password'));

        $usuario = DB::table('usuario')
            ->where('No_Usuario', $No_Usuario)
            ->where('Nu_Estado', 1)
            ->first();

        if (!$usuario) {
            return back()->withErrors([
                'error' => 'Usuario no encontrado o inactivo',
            ])->withInput($request->only('No_Usuario'));
        }

        $ciEncryption = new CodeIgniterEncryption();
        if (!$ciEncryption->verifyPassword($No_Password, $usuario->No_Password)) {
            return back()->withErrors([
                'error' => 'Contraseña incorrecta',
            ])->withInput($request->only('No_Usuario'));
        }

        $empresa = DB::table('empresa')
            ->where('ID_Empresa', $usuario->ID_Empresa)
            ->where('Nu_Estado', 1)
            ->first();

        if (!$empresa) {
            return back()->withErrors([
                'error' => 'Empresa inactiva. Comunicarse con soporte',
            ])->withInput($request->only('No_Usuario'));
        }

        Session::put('telescope_authenticated', true);
        Session::put('telescope_user_id', $usuario->ID_Usuario);
        Session::put('telescope_user_name', $usuario->No_Usuario);
        Session::put('telescope_user_email', $usuario->Txt_Email ?? '');

        Log::info('Usuario autenticado en Telescope', [
            'usuario' => $No_Usuario,
            'id' => $usuario->ID_Usuario,
        ]);

        return redirect('/' . trim((string) config('telescope.path', 'telescope'), '/'));
    }

    /**
     * Cerrar sesión de Telescope.
     */
    public function logout()
    {
        Session::forget('telescope_authenticated');
        Session::forget('telescope_user_id');
        Session::forget('telescope_user_name');
        Session::forget('telescope_user_email');

        return redirect()->route('telescope.login');
    }
}
