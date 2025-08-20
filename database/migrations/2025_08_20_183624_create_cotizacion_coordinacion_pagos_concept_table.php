<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateCotizacionCoordinacionPagosConceptTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contenedor_consolidado_pagos_concept', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            // Índices
            $table->index('name');
        });

        // Insertar conceptos iniciales
        DB::table('contenedor_consolidado_pagos_concept')->insert([
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

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cotizacion_coordinacion_pagos_concept');
    }
}
