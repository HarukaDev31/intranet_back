<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckUsuarioTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:usuario-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar la estructura de la tabla usuario';

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
        $this->info('=== Verificando estructura de la tabla usuario ===');

        try {
            // Obtener estructura de la tabla usuario
            $columns = DB::select("DESCRIBE usuario");
            
            $this->info('Columnas de la tabla usuario:');
            foreach ($columns as $column) {
                $this->line("- {$column->Field}: {$column->Type} " . ($column->Null === 'YES' ? 'NULL' : 'NOT NULL'));
            }

            // Verificar si existe ID_Grupo
            $hasIdGrupo = collect($columns)->contains('Field', 'ID_Grupo');
            
            if ($hasIdGrupo) {
                $this->info('✓ El campo ID_Grupo ya existe en la tabla usuario');
            } else {
                $this->warn('✗ El campo ID_Grupo NO existe en la tabla usuario');
            }

            // Verificar estructura de la tabla grupo
            $this->info('\n=== Verificando estructura de la tabla grupo ===');
            $grupoColumns = DB::select("DESCRIBE grupo");
            
            $this->info('Columnas de la tabla grupo:');
            foreach ($grupoColumns as $column) {
                $this->line("- {$column->Field}: {$column->Type} " . ($column->Null === 'YES' ? 'NULL' : 'NOT NULL'));
            }

            // Verificar foreign keys
            $this->info('\n=== Verificando foreign keys ===');
            $foreignKeys = DB::select("
                SELECT 
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'usuario' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            if (empty($foreignKeys)) {
                $this->warn('No se encontraron foreign keys en la tabla usuario');
            } else {
                $this->info('Foreign keys encontradas:');
                foreach ($foreignKeys as $fk) {
                    $this->line("- {$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}");
                }
            }

        } catch (\Exception $e) {
            $this->error('Error al verificar la tabla: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
