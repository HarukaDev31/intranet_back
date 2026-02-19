<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar columna estado a la tabla pivot tramite_aduana_tramite_tipo_permiso.
     * Cada par (tramite, tipo_permiso) tiene su propio estado independiente.
     */
    public function up(): void
    {
        Schema::table('tramite_aduana_tramite_tipo_permiso', function (Blueprint $table) {
            $table->string('estado', 20)->default('PENDIENTE')->after('derecho_entidad');
        });
    }

    public function down(): void
    {
        Schema::table('tramite_aduana_tramite_tipo_permiso', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
