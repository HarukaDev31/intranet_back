<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar f_inicio, f_termino, f_caducidad y dias a la tabla pivot tramite_aduana_tramite_tipo_permiso.
     * Cada tipo de permiso del trámite tiene sus propias fechas y días.
     */
    public function up(): void
    {
        Schema::table('tramite_aduana_tramite_tipo_permiso', function (Blueprint $table) {
            $table->date('f_inicio')->nullable()->after('estado');
            $table->date('f_termino')->nullable()->after('f_inicio');
            $table->date('f_caducidad')->nullable()->after('f_termino');
            $table->unsignedInteger('dias')->nullable()->after('f_caducidad');
        });
    }

    public function down(): void
    {
        Schema::table('tramite_aduana_tramite_tipo_permiso', function (Blueprint $table) {
            $table->dropColumn(['f_inicio', 'f_termino', 'f_caducidad', 'dias']);
        });
    }
};
