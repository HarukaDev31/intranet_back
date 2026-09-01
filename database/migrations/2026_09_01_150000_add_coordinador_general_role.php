<?php

use App\Models\Usuario;
use App\Support\GrupoMenuAccesoCloner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCoordinadorGeneralRole extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('grupo')) {
            return;
        }

        $now = now();
        $jefeGrupos = DB::table('grupo')
            ->where('No_Grupo', Usuario::ROL_JEFE_IMPORTACION)
            ->get();

        foreach ($jefeGrupos as $jefe) {
            $empresaId = (int) $jefe->ID_Empresa;
            $orgId = (int) $jefe->ID_Organizacion;

            $destino = DB::table('grupo')
                ->where('No_Grupo', Usuario::ROL_COORDINADOR_GENERAL)
                ->where('ID_Empresa', $empresaId)
                ->where('ID_Organizacion', $orgId)
                ->first();

            if ($destino) {
                $destinoGrupoId = (int) $destino->ID_Grupo;
            } else {
                $destinoGrupoId = (int) DB::table('grupo')->insertGetId([
                    'ID_Empresa' => $empresaId,
                    'ID_Organizacion' => $orgId,
                    'No_Grupo' => Usuario::ROL_COORDINADOR_GENERAL,
                    'No_Grupo_Descripcion' => Usuario::ROL_COORDINADOR_GENERAL,
                    'Nu_Tipo_Privilegio_Acceso' => $jefe->Nu_Tipo_Privilegio_Acceso ?? 1,
                    'Nu_Notificacion' => $jefe->Nu_Notificacion ?? 1,
                    'Nu_Estado' => $jefe->Nu_Estado ?? 1,
                ]);
            }

            GrupoMenuAccesoCloner::syncGrupoUsuarioMenus(
                (int) $jefe->ID_Grupo,
                $destinoGrupoId,
                $empresaId,
                $orgId
            );
        }

        if (Schema::hasTable('soporte_ti_areas') && Schema::hasTable('soporte_ti_area_grupo')) {
            $areaId = DB::table('soporte_ti_areas')->where('nombre', 'Importaciones')->value('id');
            if ($areaId) {
                $coordGrupos = DB::table('grupo')
                    ->where('No_Grupo', Usuario::ROL_COORDINADOR_GENERAL)
                    ->pluck('ID_Grupo');

                foreach ($coordGrupos as $grupoId) {
                    $exists = DB::table('soporte_ti_area_grupo')
                        ->where('grupo_id', (int) $grupoId)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    DB::table('soporte_ti_area_grupo')->insert([
                        'area_id' => (int) $areaId,
                        'grupo_id' => (int) $grupoId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        if (!Schema::hasTable('grupo')) {
            return;
        }

        $coordGrupos = DB::table('grupo')
            ->where('No_Grupo', Usuario::ROL_COORDINADOR_GENERAL)
            ->pluck('ID_Grupo');

        if ($coordGrupos->isEmpty()) {
            return;
        }

        if (Schema::hasTable('soporte_ti_area_grupo')) {
            DB::table('soporte_ti_area_grupo')->whereIn('grupo_id', $coordGrupos)->delete();
        }

        if (Schema::hasTable('grupo_usuario') && Schema::hasTable('menu_acceso')) {
            $grupoUsuarioIds = DB::table('grupo_usuario')
                ->whereIn('ID_Grupo', $coordGrupos)
                ->pluck('ID_Grupo_Usuario');

            if ($grupoUsuarioIds->isNotEmpty()) {
                DB::table('menu_acceso')->whereIn('ID_Grupo_Usuario', $grupoUsuarioIds)->delete();
                DB::table('grupo_usuario')->whereIn('ID_Grupo_Usuario', $grupoUsuarioIds)->delete();
            }
        }

        DB::table('grupo')->whereIn('ID_Grupo', $coordGrupos)->delete();
    }
}
