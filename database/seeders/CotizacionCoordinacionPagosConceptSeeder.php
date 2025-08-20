<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CotizacionCoordinacionPagosConceptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('cotizacion_coordinacion_pagos_concept')->insert([
            [
                'name' => 'LOGISTICA',
                'description' => 'Pago por servicios logísticos',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'IMPUESTOS',
                'description' => 'Pago de impuestos',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }
}
