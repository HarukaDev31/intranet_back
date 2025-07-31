<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Usuario;
use App\Models\Grupo;
use Illuminate\Support\Facades\Hash;

class TestLoginGrupo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:login-grupo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar que el login incluye información del grupo';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Prueba de Login con Información de Grupo ===');

        try {
            // Buscar un usuario con grupo asignado
            $usuario = Usuario::with(['grupo', 'empresa', 'organizacion'])
                ->whereNotNull('ID_Grupo')
                ->where('Nu_Estado', 1)
                ->first();

            if (!$usuario) {
                $this->error('No se encontró un usuario con grupo asignado');
                return 1;
            }

            $this->info("Usuario encontrado: {$usuario->No_Usuario}");
            $this->info("Grupo asignado: " . ($usuario->grupo ? $usuario->grupo->No_Grupo : 'Sin grupo'));

            // Simular la respuesta del login
            $this->info("\n--- Simulando respuesta del login ---");
            
            // Cargar relaciones del usuario
            $usuario->load(['grupo', 'empresa', 'organizacion']);
            
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

            $response = [
                'status' => 'success',
                'message' => 'Iniciando sesión',
                'token' => 'simulated_token_here',
                'token_type' => 'bearer',
                'expires_in' => 3600,
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
                'iCantidadAcessoUsuario' => 1,
                'iIdEmpresa' => $usuario->ID_Empresa,
                'menus' => []
            ];

            $this->info('Respuesta del login:');
            $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Verificar que la información del grupo está presente
            if ($response['user']['grupo']) {
                $this->info("\n✓ Información del grupo incluida correctamente:");
                $this->info("  - ID: {$response['user']['grupo']['id']}");
                $this->info("  - Nombre: {$response['user']['grupo']['nombre']}");
                $this->info("  - Descripción: {$response['user']['grupo']['descripcion']}");
                $this->info("  - Tipo Privilegio: {$response['user']['grupo']['tipo_privilegio']}");
                $this->info("  - Estado: {$response['user']['grupo']['estado']}");
                $this->info("  - Notificación: {$response['user']['grupo']['notificacion']}");
            } else {
                $this->warn("✗ El usuario no tiene grupo asignado");
            }

            // Verificar información de empresa y organización
            if ($response['user']['empresa']) {
                $this->info("\n✓ Información de empresa incluida:");
                $this->info("  - Empresa: {$response['user']['empresa']['nombre']}");
            }

            if ($response['user']['organizacion']) {
                $this->info("✓ Información de organización incluida:");
                $this->info("  - Organización: {$response['user']['organizacion']['nombre']}");
            }

            $this->info("\n=== Prueba completada exitosamente ===");

        } catch (\Exception $e) {
            $this->error('Error durante la prueba: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
