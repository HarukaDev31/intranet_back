<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;

class TestJWTAuth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:jwt-auth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba específica de autenticación JWT';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Prueba Específica de JWT Auth ===');
        $this->line('');

        try {
            // 1. Buscar usuario
            $this->info('1. Buscando usuario 28902...');
            $usuario = Usuario::find(28902);
            
            if (!$usuario) {
                $this->error('❌ Usuario no encontrado');
                return 1;
            }
            
            $this->line('   ✅ Usuario encontrado: ' . $usuario->No_Usuario);
            $this->line('');

            // 2. Generar token
            $this->info('2. Generando token JWT...');
            $token = JWTAuth::fromUser($usuario);
            $this->line('   ✅ Token generado: ' . substr($token, 0, 50) . '...');
            $this->line('');

            // 3. Probar autenticación con el guard 'api'
            $this->info('3. Probando autenticación con guard "api"...');
            
            // Configurar el guard
            Auth::shouldUse('api');
            
            // Intentar autenticar
            $user = JWTAuth::setToken($token)->authenticate();
            
            if ($user) {
                $this->line('   ✅ Autenticación exitosa con guard api');
                $this->line('   - Usuario: ' . $user->No_Usuario);
                $this->line('   - ID: ' . $user->ID_Usuario);
            } else {
                $this->error('   ❌ Autenticación fallida con guard api');
            }
            $this->line('');

            // 4. Probar con el token específico
            $this->info('4. Probando con token específico...');
            $specificToken = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOlwvXC9sb2NhbGhvc3Q6ODAwMFwvYXBpXC9hdXRoXC9sb2dpbiIsImlhdCI6MTc1MzU2Mzc3NCwiZXhwIjoxNzUzNTY3Mzc0LCJuYmYiOjE3NTM1NjM3NzQsImp0aSI6IlNyMXZSRllFOGNCMWtnZVIiLCJzdWIiOjI4OTAyLCJwcnYiOiI1ODcwODYzZDRhNjJkNzkxNDQzZmFmOTM2ZmMzNjgwMzFkMTEwYzRmIn0.AKEvsrW-qlqx3VZ5sxhZBTyAxekcr9qheZdJ1orpzE0";
            
            try {
                $user = JWTAuth::setToken($specificToken)->authenticate();
                if ($user) {
                    $this->line('   ✅ Token específico funciona');
                    $this->line('   - Usuario: ' . $user->No_Usuario);
                    $this->line('   - ID: ' . $user->ID_Usuario);
                } else {
                    $this->error('   ❌ Token específico no funciona');
                }
            } catch (\Exception $e) {
                $this->error('   ❌ Error con token específico: ' . $e->getMessage());
            }
            $this->line('');

            // 5. Verificar configuración del guard
            $this->info('5. Verificando configuración del guard...');
            $this->line('   - Guard configurado: ' . config('auth.defaults.guard'));
            $this->line('   - Guard API driver: ' . config('auth.guards.api.driver'));
            $this->line('   - Guard API provider: ' . config('auth.guards.api.provider'));
            $this->line('   - Provider usuarios model: ' . config('auth.providers.usuarios.model'));
            $this->line('');

            // 6. Probar parseToken directamente
            $this->info('6. Probando parseToken...');
            try {
                $payload = JWTAuth::manager()->decode(JWTAuth::setToken($specificToken)->getToken(), 'HS256');
                $this->line('   ✅ Token parseado correctamente');
                $this->line('   - Subject (user ID): ' . $payload['sub']);
                
                // Buscar usuario por ID del payload
                $userFromPayload = Usuario::find($payload['sub']);
                if ($userFromPayload) {
                    $this->line('   ✅ Usuario encontrado por ID del payload: ' . $userFromPayload->No_Usuario);
                } else {
                    $this->error('   ❌ Usuario NO encontrado por ID del payload');
                }
                
            } catch (\Exception $e) {
                $this->error('   ❌ Error parseando token: ' . $e->getMessage());
            }

        } catch (\Exception $e) {
            $this->error('❌ Error general: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
} 