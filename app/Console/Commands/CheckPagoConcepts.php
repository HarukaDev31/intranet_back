<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckPagoConcepts extends Command
{
    protected $signature = 'check:pago-concepts';
    protected $description = 'Check pago concepts';

    public function handle()
    {
        try {
            $conceptos = DB::table('cotizacion_coordinacion_pagos_concept')->get();
            
            $this->info("Conceptos de pago:");
            foreach ($conceptos as $concepto) {
                $this->line("{$concepto->id} - {$concepto->name}");
            }
            
            if ($conceptos->isEmpty()) {
                $this->warn("No hay conceptos de pago. Insertando conceptos básicos...");
                DB::table('cotizacion_coordinacion_pagos_concept')->insert([
                    ['name' => 'LOGISTICA', 'description' => 'Pago por servicios logísticos'],
                    ['name' => 'IMPUESTOS', 'description' => 'Pago de impuestos'],
                ]);
                $this->info("Conceptos insertados correctamente.");
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }
    }
} 