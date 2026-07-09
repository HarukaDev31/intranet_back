<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearAllCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia todas las cachés (cache, config, route, view) y optimiza la aplicación';

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
        $this->info('🧹 Iniciando limpieza completa del sistema...');
        $this->newLine();

        // Limpiar cache
        $this->info('🗑️  Limpiando cache...');
        $this->call('cache:clear');

        $this->line('   ✅ Cache limpiada');
        $this->newLine();

        // Limpiar config cache
        $this->info('🗑️  Limpiando config cache...');
        $this->call('config:clear');
        $this->line('   ✅ Config cache limpiada');
        $this->newLine();

        // Limpiar route cache
        $this->info('🗑️  Limpiando route cache...');
        $this->call('route:clear');
        $this->line('   ✅ Route cache limpiada');
        $this->newLine();

        // Limpiar view cache
        $this->info('🗑️  Limpiando view cache...');
        $this->call('view:clear');
        $this->line('   ✅ View cache limpiada');
        $this->newLine();        
        $this->info('✨ ¡Limpieza completa finalizada exitosamente!');
        
        return 0;
    }
}
