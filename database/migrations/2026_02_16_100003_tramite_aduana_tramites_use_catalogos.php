<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class TramiteAduanaTramitesUseCatalogos extends Migration
{
    /**
     * Truncar las tablas de catálogos de trámites de aduana para empezar con datos limpios.
     */
    public function up()
    {
        // Desactivar FK checks para poder truncar sin problemas
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        if (Schema::hasTable('tramite_aduana_tipos_permiso')) {
            DB::table('tramite_aduana_tipos_permiso')->truncate();
        }

        if (Schema::hasTable('tramite_aduana_entidades')) {
            DB::table('tramite_aduana_entidades')->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // No se puede restaurar datos truncados
    }
}
