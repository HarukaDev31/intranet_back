<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateCalculadoraTipoClienteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calculadora_tipo_cliente', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
            
            // Índices
            $table->index('nombre');
        });

        // Insertar tipos de cliente iniciales
        DB::table('calculadora_tipo_cliente')->insert([
            [
                'nombre' => 'NUEVO',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nombre' => 'INACTIVO',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nombre' => 'RECURRENTE',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nombre' => 'ANTIGUO',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nombre' => 'PREMIUM',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nombre' => 'SOCIO',
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
        Schema::dropIfExists('calculadora_tipo_cliente');
    }
}
