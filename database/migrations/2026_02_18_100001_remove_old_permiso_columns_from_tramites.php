<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eliminar las columnas id_tipo_permiso y derecho_entidad de la tabla principal de trámites.
     * Estos datos ya viven en tramite_aduana_tramite_tipo_permiso (pivot con derecho por tipo).
     */
    public function up(): void
    {
        Schema::table('consolidado_cotizacion_aduana_tramites', function (Blueprint $table) {
            $table->dropIndex(['id_tipo_permiso']);
            $table->dropColumn(['id_tipo_permiso', 'derecho_entidad']);
        });
    }

    public function down(): void
    {
        Schema::table('consolidado_cotizacion_aduana_tramites', function (Blueprint $table) {
            $table->unsignedBigInteger('id_tipo_permiso')->nullable()->after('id_entidad');
            $table->decimal('derecho_entidad', 10, 4)->default(0)->after('id_tipo_permiso');
            $table->index('id_tipo_permiso');
        });
    }
};
