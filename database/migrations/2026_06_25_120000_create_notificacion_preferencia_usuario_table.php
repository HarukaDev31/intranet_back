<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificacionPreferenciaUsuarioTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notificacion_preferencia_usuario', function (Blueprint $table) {
            $table->id();

            // Usuario dueño de la preferencia
            $table->unsignedInteger('usuario_id')->comment('ID del usuario');

            // Clave estable del tipo de notificación socket (definida en el frontend)
            $table->string('notification_key', 150)->comment('Clave del tipo de notificación websocket: ej. calendario.actividad.actualizada');

            // Canal de entrega afectado por la preferencia
            $table->string('canal', 30)->comment('Canal: modal, sonido, navegador');

            // Si el usuario quiere recibir ese aviso por ese canal
            $table->boolean('habilitado')->default(true)->comment('Si el aviso por ese canal está habilitado');

            $table->timestamps();

            // Una preferencia por usuario + tipo + canal
            $table->unique(['usuario_id', 'notification_key', 'canal'], 'notif_pref_usuario_unique');
            $table->index(['usuario_id']);

            // Clave foránea al usuario
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
        Schema::dropIfExists('notificacion_preferencia_usuario');
    }
}
