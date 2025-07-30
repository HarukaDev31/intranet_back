<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Usuario;

class TestController extends Controller
{
    public function testAuth(Request $request)
    {
        $this->info('=== Prueba de Autenticación ===');
        
        try {
            // Verificar si hay token en el header
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se proporcionó token'
                ], 401);
            }
            
            $this->info('Token encontrado: ' . substr($token, 0, 50) . '...');
            
            // Intentar autenticar
            $user = JWTAuth::setToken($token)->authenticate();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ], 401);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Autenticación exitosa',
                'user' => [
                    'id' => $user->ID_Usuario,
                    'usuario' => $user->No_Usuario,
                    'estado' => $user->Nu_Estado
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function info($message)
    {
        \Log::info($message);
    }
} 