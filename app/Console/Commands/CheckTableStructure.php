<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckTableStructure extends Command
{
    protected $signature = 'check:table-structure {table}';
    protected $description = 'Check table structure';

    public function handle()
    {
        $table = $this->argument('table');
        
        try {
            $columns = DB::select("DESCRIBE {$table}");
            
            $this->info("Structure of table {$table}:");
            foreach ($columns as $col) {
                $this->line("{$col->Field} - {$col->Type}");
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }
    }
} 