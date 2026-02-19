<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado de verificación del pago por administración: PENDIENTE | CONFIRMADO | OBSERVADO.
     */
    public function up(): void
    {
        Schema::table('tramite_aduana_pagos', function (Blueprint $table) {
            $table->string('estado_administracion', 20)->default('PENDIENTE')->after('observacion');
        });
    }

    public function down(): void
    {
        Schema::table('tramite_aduana_pagos', function (Blueprint $table) {
            $table->dropColumn('estado_administracion');
        });
    }
};
