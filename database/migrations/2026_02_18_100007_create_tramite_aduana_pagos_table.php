<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de pagos asignados por fila permiso (id_tramite + id_tipo_permiso).
     * Cuando desde el modal de "pago servicios" se selecciona un documento (voucher),
     * se guarda aquí la referencia para esa fila de permiso.
     */
    public function up(): void
    {
        Schema::create('tramite_aduana_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_tramite');
            $table->unsignedBigInteger('id_tipo_permiso');
            $table->unsignedBigInteger('id_documento')->comment('Documento de seccion pago_servicio (voucher)');
            $table->decimal('monto', 12, 2)->nullable();
            $table->date('fecha_pago')->nullable();
            $table->string('observacion', 500)->nullable();
            $table->timestamps();

            $table->unique(['id_tramite', 'id_tipo_permiso'], 'tramite_aduana_pagos_tramite_tipo_permiso_unique');
            $table->foreign('id_tramite')->references('id')->on('consolidado_cotizacion_aduana_tramites')->onDelete('cascade');
            $table->foreign('id_tipo_permiso')->references('id')->on('tramite_aduana_tipos_permiso')->onDelete('cascade');
            $table->foreign('id_documento')->references('id')->on('tramite_aduana_documentos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramite_aduana_pagos');
    }
};
