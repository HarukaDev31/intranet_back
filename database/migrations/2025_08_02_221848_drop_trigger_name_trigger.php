<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DropTriggerNameTrigger extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Eliminar el trigger problemático llamado 'trigger_name'
        DB::unprepared('DROP TRIGGER IF EXISTS trigger_name');
        
        // También eliminar cualquier otro trigger relacionado con pedido_curso
        DB::unprepared('DROP TRIGGER IF EXISTS after_pedido_curso_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS before_pedido_curso_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS pedido_curso_insert_trigger');
        DB::unprepared('DROP TRIGGER IF EXISTS after_pedido_curso_insert_safe');
        DB::unprepared('DROP TRIGGER IF EXISTS pedido_curso_queue_trigger');
        
        // Eliminar procedimientos relacionados
        DB::unprepared('DROP PROCEDURE IF EXISTS process_pedido_curso_cliente');
        DB::unprepared('DROP PROCEDURE IF EXISTS process_pedido_curso_queue');
        
        // Eliminar eventos relacionados
        DB::unprepared('DROP EVENT IF EXISTS process_pedido_curso_updates');
        DB::unprepared('DROP EVENT IF EXISTS process_pedido_curso_queue_event');
        
        // Eliminar tablas temporales si existen
        DB::unprepared('DROP TABLE IF EXISTS temp_pedido_curso_updates');
        DB::unprepared('DROP TABLE IF EXISTS pedido_curso_queue');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No necesitamos recrear nada en el rollback
        // ya que solo estamos eliminando triggers problemáticos
    }
}
