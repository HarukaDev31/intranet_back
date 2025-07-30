<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Usuario;
use Tymon\JWTAuth\Facades\JWTAuth;

class TestUsuario extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:usuario {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba si un usuario específico existe y puede autenticarse';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $id = $this->argument('id');
        
        $this->info('=== Prueba de Usuario ID: ' . $id . ' ===');
        $this->line('');

        try {
            // Buscar usuario directamente
            $this->info('1. Buscando usuario en la base de datos...');
            $usuario = Usuario::find($id);
            
            if (!$usuario) {
                $this->error('❌ Usuario no encontrado en la base de datos');
                return 1;
            }
            
            $this->line('   ✅ Usuario encontrado:');
            $this->line('   - ID: ' . $usuario->ID_Usuario);
            $this->line('   - Usuario: ' . $usuario->No_Usuario);
            $this->line('   - Estado: ' . ($usuario->Nu_Estado ? 'Activo' : 'Inactivo'));
            $this->line('   - Empresa: ' . $usuario->ID_Empresa);
            $this->line('   - Organización: ' . $usuario->ID_Organizacion);
            $this->line('');

            // Verificar si el usuario está activo
            if ($usuario->Nu_Estado != 1) {
                $this->warn('⚠️  Usuario inactivo');
                return 1;
            }
            
            $this->line('   ✅ Usuario activo');
            $this->line('');

            // Probar autenticación con JWT
            $this->info('2. Probando autenticación JWT...');
            
            try {
                // Intentar generar un token para este usuario
                $token = JWTAuth::fromUser($usuario);
                $this->line('   ✅ Token generado correctamente');
                $this->line('   - Token: ' . substr($token, 0, 50) . '...');
                $this->line('');

                // Intentar autenticar con el token
                $user = JWTAuth::setToken($token)->authenticate();
                if ($user) {
                    $this->line('   ✅ Autenticación exitosa');
                    $this->line('   - Usuario autenticado: ' . $user->No_Usuario);
                } else {
                    $this->error('   ❌ Autenticación fallida');
                }
                
            } catch (\Exception $e) {
                $this->error('   ❌ Error al generar/autenticar token: ' . $e->getMessage());
            }
            
            $this->line('');

            // Probar con el token específico
            $this->info('3. Probando con token específico...');
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

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
} 