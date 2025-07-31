<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Usuario;
use App\Models\Grupo;

class TestUsuarioGrupo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:usuario-grupo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar las relaciones entre Usuario y Grupo';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Prueba de Relaciones Usuario-Grupo ===');

        try {
            // Verificar que existen usuarios y grupos
            $usuario = Usuario::first();
            $grupo = Grupo::first();

            if (!$usuario) {
                $this->error('No hay usuarios en la base de datos');
                return 1;
            }

            if (!$grupo) {
                $this->error('No hay grupos en la base de datos');
                return 1;
            }

            $this->info("Usuario encontrado: ID {$usuario->ID_Usuario} - {$usuario->No_Usuario}");
            $this->info("Grupo encontrado: ID {$grupo->ID_Grupo} - {$grupo->No_Grupo}");

            // Probar relación directa
            $this->info("\n--- Probando relación directa ---");
            
            if ($usuario->grupo) {
                $this->info("✓ Usuario tiene grupo directo: {$usuario->grupo->No_Grupo}");
                $this->info("  Descripción: {$usuario->grupo->No_Grupo_Descripcion}");
                $this->info("  Tipo Privilegio: {$usuario->grupo->Nu_Tipo_Privilegio_Acceso}");
            } else {
                $this->warn("✗ Usuario no tiene grupo directo asignado");
            }

            // Probar atributos calculados
            $this->info("\n--- Probando atributos calculados ---");
            $this->info("Nombre Grupo Principal: {$usuario->nombre_grupo_principal}");
            $this->info("Descripción Grupo Principal: {$usuario->descripcion_grupo_principal}");
            $this->info("Tipo Privilegio Acceso: " . ($usuario->tipo_privilegio_acceso ?? 'No definido'));

            // Probar relación many-to-many
            $this->info("\n--- Probando relación many-to-many ---");
            $gruposUsuario = $usuario->gruposUsuario;
            
            if ($gruposUsuario->count() > 0) {
                $this->info("✓ Usuario tiene {$gruposUsuario->count()} grupos adicionales:");
                foreach ($gruposUsuario as $grupoUsuario) {
                    $this->info("  - {$grupoUsuario->grupo->No_Grupo} (ID: {$grupoUsuario->grupo->ID_Grupo})");
                }
            } else {
                $this->warn("✗ Usuario no tiene grupos adicionales");
            }

            // Probar método getAllGrupos
            $this->info("\n--- Probando método getAllGrupos ---");
            $todosLosGrupos = $usuario->getAllGrupos();
            $this->info("Total de grupos del usuario: {$todosLosGrupos->count()}");
            
            foreach ($todosLosGrupos as $grupo) {
                $this->info("  - {$grupo->No_Grupo} (ID: {$grupo->ID_Grupo})");
            }

            // Probar método perteneceAGrupo
            $this->info("\n--- Probando método perteneceAGrupo ---");
            $grupoId = $usuario->ID_Grupo;
            $pertenece = $usuario->perteneceAGrupo($grupoId);
            $this->info("¿Usuario pertenece al grupo {$grupoId}? " . ($pertenece ? 'Sí' : 'No'));

            // Probar relación inversa desde Grupo
            $this->info("\n--- Probando relación inversa desde Grupo ---");
            $usuariosDelGrupo = $grupo->usuarios;
            $this->info("Usuarios en el grupo '{$grupo->No_Grupo}': {$usuariosDelGrupo->count()}");
            
            foreach ($usuariosDelGrupo as $user) {
                $this->info("  - {$user->No_Usuario} (ID: {$user->ID_Usuario})");
            }

            // Probar carga de relaciones
            $this->info("\n--- Probando carga de relaciones ---");
            $usuarioConRelaciones = Usuario::with(['grupo', 'gruposUsuario.grupo'])->first();
            
            if ($usuarioConRelaciones->grupo) {
                $this->info("✓ Relación grupo cargada correctamente");
            }
            
            if ($usuarioConRelaciones->gruposUsuario->count() > 0) {
                $this->info("✓ Relación gruposUsuario cargada correctamente");
            }

            $this->info("\n=== Prueba completada exitosamente ===");

        } catch (\Exception $e) {
            $this->error('Error durante la prueba: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
