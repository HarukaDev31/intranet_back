<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crear tabla pivot tramite_aduana_tramite_tipo_permiso.
     * Cada trámite puede tener N tipos de permiso, cada uno con su propio derecho_entidad.
     * Migra los datos existentes de id_tipo_permiso + derecho_entidad de la tabla principal.
     */
    public function up(): void
    {
        Schema::create('tramite_aduana_tramite_tipo_permiso', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_tramite');
            $table->unsignedBigInteger('id_tipo_permiso');
            $table->decimal('derecho_entidad', 10, 4)->default(0);
            $table->timestamps();

            $table->index('id_tramite');
            $table->index('id_tipo_permiso');
        });

        // Migrar datos existentes: por cada trámite con id_tipo_permiso, crear fila en pivot
        if (Schema::hasColumn('consolidado_cotizacion_aduana_tramites', 'id_tipo_permiso')) {
            $tramites = DB::table('consolidado_cotizacion_aduana_tramites')
                ->whereNotNull('id_tipo_permiso')
                ->select('id', 'id_tipo_permiso', 'derecho_entidad')
                ->get();

            foreach ($tramites as $tramite) {
                DB::table('tramite_aduana_tramite_tipo_permiso')->insert([
                    'id_tramite'      => $tramite->id,
                    'id_tipo_permiso' => $tramite->id_tipo_permiso,
                    'derecho_entidad' => $tramite->derecho_entidad ?? 0,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tramite_aduana_tramite_tipo_permiso');
    }
};
