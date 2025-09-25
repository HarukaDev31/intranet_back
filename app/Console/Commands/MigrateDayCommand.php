<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migrator;

class MigrateDayCommand extends Command
{
    protected $signature = 'migrate:day {date}';
    protected $description = 'Run migrations created on a specific date';

    public function handle()
    {
        $date = $this->argument('date');
        $migrationPath = database_path('migrations');
        
        // Buscar archivos de migración por fecha
        $files = glob($migrationPath . '/' . $date . '_*.php');
        
        foreach ($files as $file) {
            $this->call('migrate', ['--path' => 'database/migrations/' . basename($file)]);
        }
    }
}