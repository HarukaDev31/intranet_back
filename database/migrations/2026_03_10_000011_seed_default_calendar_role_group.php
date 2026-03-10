<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Usuario;

class SeedDefaultCalendarRoleGroup extends Migration
{
    public function up()
    {
        // Verificar que exista la tabla de grupos de rol
        if (!DB::getSchemaBuilder()->hasTable('calendar_role_groups')) {
            return;
        }

        // Crear grupo por defecto si no existe
        $groupId = DB::table('calendar_role_groups')
            ->where('code', 'CAL_IMPORTACIONES_DEFAULT')
            ->value('id');

        if (!$groupId) {
            $groupId = DB::table('calendar_role_groups')->insertGetId([
                'name'            => 'Calendario Importaciones (por defecto)',
                'code'            => 'CAL_IMPORTACIONES_DEFAULT',
                'usa_consolidado' => true,
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        // Helper para insertar miembros evitando duplicados
        $insertMember = function ($userId, $roleType) use ($groupId) {
            $exists = DB::table('calendar_role_group_members')
                ->where('role_group_id', $groupId)
                ->where('user_id', $userId)
                ->exists();

            if (!$exists) {
                DB::table('calendar_role_group_members')->insert([
                    'role_group_id' => $groupId,
                    'user_id'       => $userId,
                    'role_type'     => $roleType,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            } else {
                // Si ya existe, solo actualizamos el tipo de rol
                DB::table('calendar_role_group_members')
                    ->where('role_group_id', $groupId)
                    ->where('user_id', $userId)
                    ->update([
                        'role_type'  => $roleType,
                        'updated_at' => now(),
                    ]);
            }
        };

        // JEFES DE IMPORTACIÓN
        $jefes = Usuario::whereHas('grupo', function ($q) {
                $q->where('No_Grupo', Usuario::ROL_JEFE_IMPORTACION);
            })
            ->where('Nu_Estado', 1)
            ->pluck('ID_Usuario');

        foreach ($jefes as $userId) {
            $insertMember($userId, 'JEFE');
        }

        // COORDINACIÓN (como miembros del grupo)
        $coordinadores = Usuario::whereHas('grupo', function ($q) {
                $q->where('No_Grupo', Usuario::ROL_COORDINACION);
            })
            ->where('Nu_Estado', 1)
            ->pluck('ID_Usuario');

        foreach ($coordinadores as $userId) {
            $insertMember($userId, 'MIEMBRO');
        }

        // DOCUMENTACIÓN (como miembros del grupo)
        $documentacion = Usuario::whereHas('grupo', function ($q) {
                $q->where('No_Grupo', Usuario::ROL_DOCUMENTACION);
            })
            ->where('Nu_Estado', 1)
            ->pluck('ID_Usuario');

        foreach ($documentacion as $userId) {
            $insertMember($userId, 'MIEMBRO');
        }
    }

    public function down()
    {
        // Opcional: eliminar sólo el grupo sembrado por esta migración
        $groupId = DB::table('calendar_role_groups')
            ->where('code', 'CAL_IMPORTACIONES_DEFAULT')
            ->value('id');

        if ($groupId) {
            DB::table('calendar_role_group_members')
                ->where('role_group_id', $groupId)
                ->delete();

            DB::table('calendar_role_groups')
                ->where('id', $groupId)
                ->delete();
        }
    }
}

