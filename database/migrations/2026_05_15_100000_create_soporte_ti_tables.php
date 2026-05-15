<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSoporteTiTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('soporte_ti_estados')) {
            return;
        }

        Schema::create('soporte_ti_estados', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 80);
            $table->char('tipo_solicitud', 1)->nullable()->comment('A, B o null = ambos');
            $table->unsignedTinyInteger('orden_kanban')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('soporte_ti_solicitudes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 20)->unique();
            $table->char('tipo_solicitud', 1);
            $table->char('subtipo_b', 2)->nullable();
            $table->string('titulo', 255);
            $table->string('area', 80);
            $table->string('solicitante', 120);
            $table->unsignedInteger('solicitante_user_id')->nullable();
            $table->string('pm', 120)->nullable();
            $table->unsignedInteger('pm_user_id')->nullable();
            $table->string('analista', 120)->nullable()->default('Por asignar');
            $table->unsignedInteger('analista_user_id')->nullable();
            $table->string('criticidad', 40)->default('Por definir');
            $table->unsignedTinyInteger('estado_actual_id')->default(1);
            $table->unsignedTinyInteger('fase_index')->default(0);
            $table->unsignedTinyInteger('progreso')->default(0);
            $table->unsignedSmallInteger('sla_horas')->default(8);
            $table->decimal('horas_transcurridas', 8, 2)->default(0);
            $table->date('fecha_fin_estimado')->nullable();
            $table->string('seccion_ruta', 255)->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->timestamps();

            $table->foreign('estado_actual_id')->references('id')->on('soporte_ti_estados');
            $table->index('estado_actual_id');
            $table->index('tipo_solicitud');
        });

        Schema::create('soporte_ti_solicitud_estados', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('solicitud_id');
            $table->unsignedTinyInteger('estado_id');
            $table->unsignedTinyInteger('estado_anterior_id')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->text('comentario')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('solicitud_id')->references('id')->on('soporte_ti_solicitudes')->onDelete('cascade');
            $table->foreign('usuario_id')->references('ID_Usuario')->on('usuario')->onDelete('set null');
            $table->foreign('estado_id')->references('id')->on('soporte_ti_estados');
            $table->foreign('estado_anterior_id')->references('id')->on('soporte_ti_estados');
            $table->index('solicitud_id');
        });

        Schema::create('soporte_ti_estado_transiciones', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedTinyInteger('estado_origen_id');
            $table->unsignedTinyInteger('estado_destino_id');
            $table->string('rol', 30);
            $table->char('tipo_solicitud', 1)->nullable();
            $table->unique(
                ['estado_origen_id', 'estado_destino_id', 'rol', 'tipo_solicitud'],
                'uk_soporte_ti_transicion'
            );
            $table->foreign('estado_origen_id')->references('id')->on('soporte_ti_estados');
            $table->foreign('estado_destino_id')->references('id')->on('soporte_ti_estados');
        });

        Schema::create('soporte_ti_chat_salas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('chat_uuid', 36)->unique();
            $table->unsignedBigInteger('solicitud_id')->unique();
            $table->timestamps();

            $table->foreign('solicitud_id')->references('id')->on('soporte_ti_solicitudes')->onDelete('cascade');
        });

        Schema::create('soporte_ti_chat_miembros', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sala_id');
            $table->unsignedInteger('usuario_id');
            $table->string('rol_en_ticket', 30)->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->unique(['sala_id', 'usuario_id']);

            $table->foreign('sala_id')->references('id')->on('soporte_ti_chat_salas')->onDelete('cascade');
            $table->foreign('usuario_id')->references('ID_Usuario')->on('usuario')->onDelete('cascade');
        });

        Schema::create('soporte_ti_mensajes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sala_id');
            $table->unsignedInteger('usuario_id')->nullable();
            $table->string('remitente', 120);
            $table->string('iniciales', 8);
            $table->string('color', 16)->default('#64748b');
            $table->text('texto')->nullable();
            $table->boolean('es_sistema')->default(false);
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->string('archivo_nombre', 255)->nullable();
            $table->timestamps();

            $table->foreign('sala_id')->references('id')->on('soporte_ti_chat_salas')->onDelete('cascade');
            $table->foreign('reply_to_id')->references('id')->on('soporte_ti_mensajes')->onDelete('set null');
            $table->index(['sala_id', 'id']);
        });

        Schema::create('soporte_ti_mensaje_imagenes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('mensaje_id');
            $table->string('url', 500);
            $table->string('nombre', 255);
            $table->string('tamano', 32)->nullable();
            $table->unsignedTinyInteger('orden')->default(0);

            $table->foreign('mensaje_id')->references('id')->on('soporte_ti_mensajes')->onDelete('cascade');
        });

        Schema::create('soporte_ti_maquetas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('solicitud_id');
            $table->string('nombre', 255);
            $table->string('tamano', 32)->nullable();
            $table->string('ruta_archivo', 500)->nullable();
            $table->string('url_preview', 500)->nullable();
            $table->date('fecha_entrega')->nullable();
            $table->boolean('aprobada')->default(false);
            $table->unsignedInteger('subida_por_user_id')->nullable();
            $table->timestamps();

            $table->foreign('solicitud_id')->references('id')->on('soporte_ti_solicitudes')->onDelete('cascade');
            $table->foreign('subida_por_user_id')->references('ID_Usuario')->on('usuario')->onDelete('set null');
            $table->unique('solicitud_id');
        });

        DB::table('soporte_ti_estados')->insert([
            ['id' => 1, 'codigo' => 'pendiente', 'nombre' => 'Pendiente', 'tipo_solicitud' => null, 'orden_kanban' => 1, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'codigo' => 'en_maqueta', 'nombre' => 'En maqueta', 'tipo_solicitud' => 'A', 'orden_kanban' => 2, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'codigo' => 'en_progreso', 'nombre' => 'En progreso', 'tipo_solicitud' => null, 'orden_kanban' => 3, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'codigo' => 'hecho', 'nombre' => 'Hecho', 'tipo_solicitud' => 'B', 'orden_kanban' => 4, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'codigo' => 'desplegado', 'nombre' => 'Desplegado', 'tipo_solicitud' => null, 'orden_kanban' => 5, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'codigo' => 'observado', 'nombre' => 'Observado', 'tipo_solicitud' => null, 'orden_kanban' => 6, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'codigo' => 'operativo', 'nombre' => 'Operativo', 'tipo_solicitud' => null, 'orden_kanban' => 7, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('soporte_ti_maquetas');
        Schema::dropIfExists('soporte_ti_mensaje_imagenes');
        Schema::dropIfExists('soporte_ti_mensajes');
        Schema::dropIfExists('soporte_ti_chat_miembros');
        Schema::dropIfExists('soporte_ti_chat_salas');
        Schema::dropIfExists('soporte_ti_estado_transiciones');
        Schema::dropIfExists('soporte_ti_solicitud_estados');
        Schema::dropIfExists('soporte_ti_solicitudes');
        Schema::dropIfExists('soporte_ti_estados');
    }
}
