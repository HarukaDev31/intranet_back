<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('consolidado_cotizacion_aduana_tramites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_cotizacion')->nullable();
            $table->unsignedBigInteger('id_consolidado');
            $table->unsignedBigInteger('id_cliente')->nullable();
            $table->unsignedBigInteger('id_entidad');
            $table->unsignedBigInteger('id_tipo_permiso');
            $table->decimal('derecho_entidad', 10, 4)->default(0);
            $table->decimal('precio', 10, 4)->default(0);
            $table->date('f_inicio')->nullable();
            $table->date('f_termino')->nullable();
            $table->date('f_caducidad')->nullable();
            $table->unsignedInteger('dias')->nullable();
            $table->enum('estado', [
                'PENDIENTE',
                'SD',
                'PAGADO',
                'EN_TRAMITE',
                'RECHAZADO',
                'COMPLETADO'
            ])->default('PENDIENTE');
            $table->timestamps();

            $table->index('id_cotizacion');
            $table->index('id_consolidado');
            $table->index('id_cliente');
            $table->index('id_entidad');
            $table->index('id_tipo_permiso');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consolidado_cotizacion_aduana_tramites');
    }
};
