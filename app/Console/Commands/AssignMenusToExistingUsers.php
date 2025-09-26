<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignMenusToExistingUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:assign-menus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Asignar todos los menús de menu_user a usuarios existentes que no los tengan';

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
        $this->info('Iniciando asignación de menús a usuarios existentes...');

        try {
            // Obtener todos los usuarios
            $users = DB::table('users')->select('id')->get();
            $this->info("Encontrados {$users->count()} usuarios");
            //drop table menu_user_access
            DB::table('menu_user_access')->truncate();
            // Obtener todos los menús disponibles
            $menus = DB::table('menu_user')->select('ID_Menu')->get();
            $this->info("Encontrados {$menus->count()} menús disponibles");

            $totalAssigned = 0;

            foreach ($users as $user) {
                // Verificar qué menús ya tiene el usuario
                $existingMenus = DB::table('menu_user_access')
                    ->where('user_id', $user->id)
                    ->pluck('ID_Menu')
                    ->toArray();

                // Preparar menús a asignar (los que no tiene)
                $menusToAssign = [];
                foreach ($menus as $menu) {
                    if (!in_array($menu->ID_Menu, $existingMenus)) {
                        $menusToAssign[] = [
                            'ID_Menu' => $menu->ID_Menu,
                            'user_id' => $user->id,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                }

                // Insertar los menús que faltan
                if (!empty($menusToAssign)) {
                    DB::table('menu_user_access')->insert($menusToAssign);
                    $totalAssigned += count($menusToAssign);
                    $this->line("Usuario ID {$user->id}: asignados " . count($menusToAssign) . " menús");
                }
            }

            $this->info("✅ Proceso completado. Total de asignaciones realizadas: {$totalAssigned}");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante el proceso: " . $e->getMessage());
            return 1;
        }
    }
}
