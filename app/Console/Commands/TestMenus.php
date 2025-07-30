<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestMenus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:menus {--user=} {--grupo=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba la funcionalidad de menús';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $usuario = $this->option('user') ?? 'test';
        $idGrupo = $this->option('grupo') ?? 1;

        $this->info('=== Prueba de Funcionalidad de Menús ===');
        $this->line('');

        try {
            // Simular usuario para la prueba
            $user = (object) [
                'ID_Grupo' => $idGrupo,
                'No_Usuario' => $usuario
            ];

            $menus = $this->obtenerMenusUsuario($user);

            $this->info('Usuario: ' . $usuario);
            $this->info('ID_Grupo: ' . $idGrupo);
            $this->line('');

            if (empty($menus)) {
                $this->warn('No se encontraron menús para este usuario/grupo');
                $this->line('');
                $this->info('Verificando tablas de menús...');
                
                // Verificar si las tablas existen
                $tables = ['menu', 'menu_acceso', 'grupo_usuario'];
                foreach ($tables as $table) {
                    try {
                        $count = DB::table($table)->count();
                        $this->line("   Tabla {$table}: {$count} registros");
                    } catch (\Exception $e) {
                        $this->error("   Tabla {$table}: No existe o error de acceso");
                    }
                }
            } else {
                $this->info('Menús encontrados: ' . count($menus));
                $this->line('');

                foreach ($menus as $index => $menu) {
                    $this->line(($index + 1) . '. ' . ($menu->No_Menu ?? 'Sin nombre') . ' (ID: ' . $menu->ID_Menu . ')');
                    
                    if (isset($menu->Hijos) && !empty($menu->Hijos)) {
                        foreach ($menu->Hijos as $hijoIndex => $hijo) {
                            $this->line('   └── ' . ($hijo->No_Menu ?? 'Sin nombre') . ' (ID: ' . $hijo->ID_Menu . ')');
                            
                            if (isset($hijo->SubHijos) && !empty($hijo->SubHijos)) {
                                foreach ($hijo->SubHijos as $subHijo) {
                                    $this->line('       └── ' . ($subHijo->No_Menu ?? 'Sin nombre') . ' (ID: ' . $subHijo->ID_Menu . ')');
                                }
                            }
                        }
                    }
                    $this->line('');
                }
            }

            $this->info('✅ Prueba completada');

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Obtener menús del usuario (método copiado del AuthController)
     */
    private function obtenerMenusUsuario($usuario)
    {
        try {
            $idGrupo = $usuario->ID_Grupo;
            $noUsuario = $usuario->No_Usuario;

            // Configurar condiciones según el usuario
            $selectDistinct = "";
            $whereIdGrupo = "AND GRPUSR.ID_Grupo = " . $idGrupo;
            $orderByNuAgregar = "";

            if ($noUsuario == 'root') {
                $selectDistinct = "DISTINCT";
                $whereIdGrupo = "";
                $orderByNuAgregar = "ORDER BY Nu_Agregar DESC";
            }

            // Obtener menús padre
            $sqlPadre = "SELECT {$selectDistinct}
                        MNU.*,
                        (SELECT COUNT(*) FROM menu WHERE ID_Padre = MNU.ID_Menu AND Nu_Activo = 0) AS Nu_Cantidad_Menu_Padre
                        FROM menu AS MNU
                        JOIN menu_acceso AS MNUACCESS ON (MNU.ID_Menu = MNUACCESS.ID_Menu)
                        JOIN grupo_usuario AS GRPUSR ON (GRPUSR.ID_Grupo_Usuario = MNUACCESS.ID_Grupo_Usuario)
                        WHERE MNU.ID_Padre = 0
                        AND MNU.Nu_Activo = 0
                        {$whereIdGrupo}
                        ORDER BY MNU.ID_Padre ASC, MNU.Nu_Orden, MNU.ID_MENU ASC";

            $arrMenuPadre = DB::select($sqlPadre);

            // Obtener hijos para cada menú padre
            foreach ($arrMenuPadre as $rowPadre) {
                $sqlHijos = "SELECT {$selectDistinct}
                            MNU.*,
                            (SELECT COUNT(*) FROM menu WHERE ID_Padre = MNU.ID_Menu AND Nu_Activo = 0) AS Nu_Cantidad_Menu_Hijos
                            FROM menu AS MNU
                            JOIN menu_acceso AS MNUACCESS ON (MNU.ID_Menu = MNUACCESS.ID_Menu)
                            JOIN grupo_usuario AS GRPUSR ON (GRPUSR.ID_Grupo_Usuario = MNUACCESS.ID_Grupo_Usuario)
                            WHERE MNU.ID_Padre = ?
                            AND MNU.Nu_Activo = 0
                            {$whereIdGrupo}
                            ORDER BY MNU.Nu_Orden";

                $rowPadre->Hijos = DB::select($sqlHijos, [$rowPadre->ID_Menu]);

                // Obtener sub-hijos para cada hijo
                foreach ($rowPadre->Hijos as $rowSubHijos) {
                    if ($rowSubHijos->Nu_Cantidad_Menu_Hijos > 0) {
                        $sqlSubHijos = "SELECT {$selectDistinct}
                                       MNU.*
                                       FROM menu AS MNU
                                       JOIN menu_acceso AS MNUACCESS ON (MNU.ID_Menu = MNUACCESS.ID_Menu)
                                       JOIN grupo_usuario AS GRPUSR ON (GRPUSR.ID_Grupo_Usuario = MNUACCESS.ID_Grupo_Usuario)
                                       WHERE MNU.ID_Padre = ?
                                       AND MNU.Nu_Activo = 0
                                       {$whereIdGrupo}
                                       ORDER BY MNU.Nu_Orden";

                        $rowSubHijos->SubHijos = DB::select($sqlSubHijos, [$rowSubHijos->ID_Menu]);
                    } else {
                        $rowSubHijos->SubHijos = [];
                    }
                }
            }

            return $arrMenuPadre;

        } catch (\Exception $e) {
            // En caso de error, devolver array vacío
            return [];
        }
    }
} 