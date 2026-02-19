<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar id_tipo_permiso y seccion a tramite_aduana_documentos.
     * - id_tipo_permiso null = documento compartido del trámite (pago_servicio, seguimiento)
     * - id_tipo_permiso set = documento propio de ese tipo de permiso (documentos_tramite, fotos)
     * - seccion: 'documentos_tramite' | 'fotos' | 'pago_servicio' | 'seguimiento'
     */
    public function up(): void
    {
        Schema::table('tramite_aduana_documentos', function (Blueprint $table) {
            $table->unsignedBigInteger('id_tipo_permiso')->nullable()->after('id_tramite');
            $table->string('seccion', 50)->default('documentos_tramite')->after('id_tipo_permiso');
            $table->index('id_tipo_permiso');
            $table->index(['id_tramite', 'seccion']);
        });
    }

    public function down(): void
    {
        Schema::table('tramite_aduana_documentos', function (Blueprint $table) {
            $table->dropIndex(['id_tipo_permiso']);
            $table->dropIndex(['id_tramite', 'seccion']);
            $table->dropColumn(['id_tipo_permiso', 'seccion']);
        });
    }
};
