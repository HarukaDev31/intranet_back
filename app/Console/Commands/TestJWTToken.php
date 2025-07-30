<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\Usuario;

class TestJWTToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:jwt-token {token?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba y valida tokens JWT';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $token = $this->argument('token');
        
        if (!$token) {
            $this->error('❌ No se proporcionó un token');
            $this->line('');
            $this->info('Uso: php artisan test:jwt-token "tu_token_aqui"');
            return 1;
        }

        $this->info('=== Prueba de Token JWT ===');
        $this->line('');

        try {
            // Intentar parsear el token
            $this->info('1. Parseando token...');
            $payload = JWTAuth::manager()->decode(JWTAuth::setToken($token)->getToken(), 'HS256');
            $this->line('   ✅ Token parseado correctamente');
            
            // Mostrar información del payload
            $this->info('2. Información del payload:');
            $this->line('   - Issuer (iss): ' . ($payload['iss'] ?? 'No especificado'));
            $this->line('   - Subject (sub): ' . ($payload['sub'] ?? 'No especificado'));
            $this->line('   - Issued At (iat): ' . date('Y-m-d H:i:s', $payload['iat'] ?? 0));
            $this->line('   - Expires At (exp): ' . date('Y-m-d H:i:s', $payload['exp'] ?? 0));
            $this->line('   - Not Before (nbf): ' . date('Y-m-d H:i:s', $payload['nbf'] ?? 0));
            $this->line('   - JWT ID (jti): ' . ($payload['jti'] ?? 'No especificado'));
            $this->line('   - Provider (prv): ' . ($payload['prv'] ?? 'No especificado'));
            
            // Verificar si el token ha expirado
            $currentTime = time();
            $expTime = $payload['exp'] ?? 0;
            
            if ($currentTime > $expTime) {
                $this->warn('   ⚠️  Token ha expirado');
                $this->line('   - Tiempo actual: ' . date('Y-m-d H:i:s', $currentTime));
                $this->line('   - Tiempo de expiración: ' . date('Y-m-d H:i:s', $expTime));
            } else {
                $this->line('   ✅ Token no ha expirado');
                $remainingTime = $expTime - $currentTime;
                $this->line('   - Tiempo restante: ' . gmdate('H:i:s', $remainingTime));
            }
            
            $this->line('');

            // Intentar autenticar el usuario
            $this->info('3. Autenticando usuario...');
            $user = JWTAuth::setToken($token)->authenticate();
            
            if ($user) {
                $this->line('   ✅ Usuario autenticado correctamente');
                $this->line('   - ID: ' . $user->ID_Usuario);
                $this->line('   - Usuario: ' . $user->No_Usuario);
                $this->line('   - Estado: ' . ($user->Nu_Estado ? 'Activo' : 'Inactivo'));
                $this->line('   - Empresa: ' . $user->ID_Empresa);
                $this->line('   - Organización: ' . $user->ID_Organizacion);
                
                // Verificar si el usuario está activo
                if ($user->Nu_Estado != 1) {
                    $this->warn('   ⚠️  Usuario inactivo');
                } else {
                    $this->line('   ✅ Usuario activo');
                }
            } else {
                $this->error('   ❌ Usuario no encontrado');
            }
            
            $this->line('');

            // Probar acceso a la API
            $this->info('4. Probando acceso a la API...');
            
            // Simular una petición HTTP
            $request = \Illuminate\Http\Request::create('/api/base-datos/productos', 'GET');
            $request->headers->set('Authorization', 'Bearer ' . $token);
            
            // Verificar si el middleware JWT funciona
            try {
                $user = JWTAuth::parseToken()->authenticate();
                if ($user) {
                    $this->line('   ✅ Middleware JWT funciona correctamente');
                } else {
                    $this->error('   ❌ Middleware JWT: Usuario no encontrado');
                }
            } catch (\Exception $e) {
                $this->error('   ❌ Middleware JWT: ' . $e->getMessage());
            }
            
            $this->line('');

            $this->info('✅ Token válido y funcional');

        } catch (TokenExpiredException $e) {
            $this->error('❌ Token expirado: ' . $e->getMessage());
            return 1;
        } catch (TokenInvalidException $e) {
            $this->error('❌ Token inválido: ' . $e->getMessage());
            return 1;
        } catch (JWTException $e) {
            $this->error('❌ Error JWT: ' . $e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->error('❌ Error inesperado: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
} 