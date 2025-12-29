<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Helpers\CodeIgniterEncryption;
use Illuminate\Support\Facades\Log;

class LogViewerLoginController extends Controller
{
    /**
     * Mostrar formulario de login
     */
    public function showLoginForm()
    {
        // Si ya está autenticado, redirigir al visor de logs
        if (Session::has('logviewer_authenticated')) {
            return redirect()->route('logs');
        }

        return view('logviewer.login');
    }

    /**
     * Procesar login
     */
    public function login(Request $request)
    {
        $request->validate([
            'No_Usuario' => 'required|string',
            'No_Password' => 'required|string',
        ]);

        $No_Usuario = trim($request->input('No_Usuario'));
        $No_Password = trim($request->input('No_Password'));

        // Buscar usuario
        $usuario = DB::table('usuario')
            ->where('No_Usuario', $No_Usuario)
            ->where('Nu_Estado', 1)
            ->first();

        if (!$usuario) {
            return back()->withErrors([
                'error' => 'Usuario no encontrado o inactivo'
            ])->withInput($request->only('No_Usuario'));
        }

        // Verificar contraseña usando encriptación de CodeIgniter
        $ciEncryption = new CodeIgniterEncryption();
        if (!$ciEncryption->verifyPassword($No_Password, $usuario->No_Password)) {
            return back()->withErrors([
                'error' => 'Contraseña incorrecta'
            ])->withInput($request->only('No_Usuario'));
        }

        // Verificar estado de empresa
        $empresa = DB::table('empresa')
            ->where('ID_Empresa', $usuario->ID_Empresa)
            ->where('Nu_Estado', 1)
            ->first();

        if (!$empresa) {
            return back()->withErrors([
                'error' => 'Empresa inactiva. Comunicarse con soporte'
            ])->withInput($request->only('No_Usuario'));
        }

        // Autenticación exitosa - crear sesión
        Session::put('logviewer_authenticated', true);
        Session::put('logviewer_user_id', $usuario->ID_Usuario);
        Session::put('logviewer_user_name', $usuario->No_Usuario);

        Log::info('Usuario autenticado en visor de logs', [
            'usuario' => $No_Usuario,
            'id' => $usuario->ID_Usuario
        ]);

        return redirect()->route('logs');
    }

    /**
     * Cerrar sesión
     */
    public function logout()
    {
        Session::forget('logviewer_authenticated');
        Session::forget('logviewer_user_id');
        Session::forget('logviewer_user_name');

        return redirect()->route('logviewer.login');
    }
}

