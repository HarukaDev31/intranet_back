<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateOrganizacionPaisesHabilitadosTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('organizacion_paises_habilitados')) {
            Schema::create('organizacion_paises_habilitados', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('organizacion_id');
                $table->unsignedInteger('id_pais');
                $table->unique(['organizacion_id', 'id_pais'], 'org_pais_habilitado_unique');
                $table->index('organizacion_id', 'org_pais_habilitado_org_idx');
            });
        }

        if (!Schema::hasTable('organizacion') || !Schema::hasColumn('organizacion', 'id_pais')) {
            return;
        }

        $orgs = DB::table('organizacion')
            ->whereNotNull('id_pais')
            ->where('id_pais', '>', 0)
            ->get(['ID_Organizacion', 'id_pais']);

        foreach ($orgs as $org) {
            $exists = DB::table('organizacion_paises_habilitados')
                ->where('organizacion_id', $org->ID_Organizacion)
                ->where('id_pais', $org->id_pais)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('organizacion_paises_habilitados')->insert([
                'organizacion_id' => $org->ID_Organizacion,
                'id_pais' => $org->id_pais,
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('organizacion_paises_habilitados');
    }
}
