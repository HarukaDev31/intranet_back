<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalculadoraTarifasConsolidadoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //IF EXISTS DROP TABLE
        if (Schema::hasTable('calculadora_tarifas_consolidado')) {
            Schema::dropIfExists('calculadora_tarifas_consolidado');
        }

        Schema::create('calculadora_tarifas_consolidado', function (Blueprint $table) {
            $table->id();
            $table->decimal('limit_inf', 15, 2);
            $table->decimal('limit_sup', 15, 2);
            $table->decimal('value', 15, 2);
            $table->enum('type', ['PLAIN', 'STANDARD']);
            $table->unsignedBigInteger('calculadora_tipo_cliente_id');
            $table->timestamps();
            $table->softDeletes();
            
            // Clave foránea
            $table->foreign('calculadora_tipo_cliente_id', 'fk_tarifas_tipo_cliente')
                  ->references('id')
                  ->on('calculadora_tipo_cliente')
                  ->onDelete('cascade');
            
            // Índices
            $table->index(['limit_inf', 'limit_sup'], 'idx_tarifas_limits');
            $table->index('calculadora_tipo_cliente_id', 'idx_tarifas_tipo_cliente');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calculadora_tarifas_consolidado');
    }
}
