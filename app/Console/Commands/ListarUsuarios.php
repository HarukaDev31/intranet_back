<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Usuario;

class ListarUsuarios extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usuarios:listar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lista todos los usuarios en la base de datos';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Usuarios en la Base de Datos ===');
        $this->line('');

        try {
            $usuarios = Usuario::select('ID_Usuario', 'No_Usuario', 'Nu_Estado', 'ID_Empresa', 'ID_Organizacion')
                ->orderBy('ID_Usuario')
                ->get();

            if ($usuarios->isEmpty()) {
                $this->warn('No hay usuarios en la base de datos');
                return 0;
            }

            $this->table(
                ['ID', 'Usuario', 'Estado', 'Empresa', 'Organización'],
                $usuarios->map(function ($usuario) {
                    return [
                        $usuario->ID_Usuario,
                        $usuario->No_Usuario,
                        $usuario->Nu_Estado ? 'Activo' : 'Inactivo',
                        $usuario->ID_Empresa,
                        $usuario->ID_Organizacion
                    ];
                })->toArray()
            );

            $this->line('');
            $this->info('Total de usuarios: ' . $usuarios->count());
            $this->info('Usuarios activos: ' . $usuarios->where('Nu_Estado', 1)->count());
            $this->info('Usuarios inactivos: ' . $usuarios->where('Nu_Estado', 0)->count());

        } catch (\Exception $e) {
            $this->error('Error al obtener usuarios: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
} 