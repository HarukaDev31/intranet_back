<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificacionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            
            // Información básica de la notificación
            $table->string('titulo', 255)->comment('Título de la notificación');
            $table->text('mensaje')->comment('Mensaje de la notificación');
            $table->text('descripcion')->nullable()->comment('Descripción detallada de la notificación');
            
            // Configuración por rol - textos específicos por rol
            $table->json('configuracion_roles')->nullable()->comment('Configuración específica de texto por rol: {rol: {titulo, mensaje, descripcion}}');
            
            // Información de destino
            $table->string('modulo', 100)->comment('Módulo al que pertenece: BaseDatos, CargaConsolidada, Cursos, etc.');
            $table->string('rol_destinatario', 100)->nullable()->comment('Rol específico destinatario (null = todos los roles)');
            $table->unsignedBigInteger('usuario_destinatario')->nullable()->comment('Usuario específico destinatario (null = todos los usuarios del rol)');
            
            // Navegación
            $table->string('navigate_to', 500)->nullable()->comment('URL o ruta para redirección en el frontend');
            $table->json('navigate_params')->nullable()->comment('Parámetros adicionales para la navegación');
            
            // Información adicional
            $table->string('tipo', 50)->default('info')->comment('Tipo de notificación: info, success, warning, error');
            $table->string('icono', 100)->nullable()->comment('Icono a mostrar en la notificación');
            $table->integer('prioridad')->default(1)->comment('Prioridad de la notificación (1=baja, 5=alta)');
            
            // Referencias
            $table->string('referencia_tipo', 100)->nullable()->comment('Tipo de entidad referenciada');
            $table->unsignedBigInteger('referencia_id')->nullable()->comment('ID de la entidad referenciada');
            
            // Control
            $table->boolean('activa')->default(true)->comment('Si la notificación está activa');
            $table->timestamp('fecha_expiracion')->nullable()->comment('Fecha de expiración de la notificación');
            $table->unsignedBigInteger('creado_por')->comment('ID del usuario que creó la notificación');
            
            $table->timestamps();
            
            // Índices
            $table->index(['modulo', 'rol_destinatario']);
            $table->index(['usuario_destinatario']);
            $table->index(['activa', 'fecha_expiracion']);
            $table->index(['referencia_tipo', 'referencia_id']);
            $table->index(['creado_por']);
            
            // Claves foráneas
            $table->foreign('usuario_destinatario')->references('ID_Usuario')->on('usuario')->onDelete('cascade');
            $table->foreign('creado_por')->references('ID_Usuario')->on('usuario')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notificaciones');
    }
}
