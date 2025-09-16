<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificacionUsuarioTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notificacion_usuario', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->unsignedBigInteger('notificacion_id')->comment('ID de la notificación');
            $table->unsignedInteger('usuario_id')->comment('ID del usuario');
            
            // Estado de la notificación para el usuario
            $table->boolean('leida')->default(false)->comment('Si el usuario ha leído la notificación');
            $table->timestamp('fecha_lectura')->nullable()->comment('Fecha y hora en que se marcó como leída');
            $table->boolean('archivada')->default(false)->comment('Si el usuario archivó la notificación');
            $table->timestamp('fecha_archivado')->nullable()->comment('Fecha y hora en que se archivó');
            
            $table->timestamps();
            
            // Índices
            $table->unique(['notificacion_id', 'usuario_id'], 'notif_usuario_unique');
            $table->index(['usuario_id', 'leida']);
            $table->index(['usuario_id', 'archivada']);
            $table->index(['notificacion_id']);
            
            // Claves foráneas
            $table->foreign('notificacion_id')->references('id')->on('notificaciones')->onDelete('cascade');
            $table->foreign('usuario_id')->references('ID_Usuario')->on('usuario')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notificacion_usuario');
    }
}
