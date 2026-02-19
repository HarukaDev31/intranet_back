<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Añade seccion e id_tipo_permiso a tramite_aduana_categorias.
     * - seccion: 'documentos_tramite' | 'seguimiento'
     * - id_tipo_permiso: nullable; si set, la categoría es de ese tipo (por tab); null = compartida (ej. RH).
     * Sustituye unique(id_tramite, nombre) por unique(id_tramite, nombre, seccion, id_tipo_permiso).
     */
    public function up(): void
    {
        Schema::table('tramite_aduana_categorias', function (Blueprint $table) {
            $table->dropUnique(['id_tramite', 'nombre']);
        });
        Schema::table('tramite_aduana_categorias', function (Blueprint $table) {
            $table->string('seccion', 50)->default('documentos_tramite')->after('nombre');
            $table->unsignedBigInteger('id_tipo_permiso')->nullable()->after('seccion');
            $table->index(['id_tramite', 'seccion']);
            $table->index('id_tipo_permiso');
            $table->unique(['id_tramite', 'nombre', 'seccion', 'id_tipo_permiso'], 'tramite_cat_nombre_seccion_tipo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tramite_aduana_categorias', function (Blueprint $table) {
            $table->dropUnique('tramite_cat_nombre_seccion_tipo_unique');
            $table->dropIndex(['id_tramite', 'seccion']);
            $table->dropIndex(['id_tipo_permiso']);
            $table->dropColumn(['seccion', 'id_tipo_permiso']);
        });
        Schema::table('tramite_aduana_categorias', function (Blueprint $table) {
            $table->unique(['id_tramite', 'nombre']);
        });
    }
};
