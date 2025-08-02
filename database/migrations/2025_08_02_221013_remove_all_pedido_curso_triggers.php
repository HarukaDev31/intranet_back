<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class RemoveAllPedidoCursoTriggers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Eliminar TODOS los triggers relacionados con pedido_curso
        DB::unprepared('DROP TRIGGER IF EXISTS after_pedido_curso_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS before_pedido_curso_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS pedido_curso_insert_trigger');
        
        // Eliminar eventos relacionados
        DB::unprepared('DROP EVENT IF EXISTS process_pedido_curso_updates');
        
        // Eliminar tablas temporales si existen
        DB::unprepared('DROP TABLE IF EXISTS temp_pedido_curso_updates');
        
        // Crear un procedimiento almacenado que maneje la lógica
     
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar el trigger
        DB::unprepared('DROP TRIGGER IF EXISTS after_pedido_curso_insert_safe');
        
        // Eliminar el procedimiento
        DB::unprepared('DROP PROCEDURE IF EXISTS process_pedido_curso_cliente');
    }
}
