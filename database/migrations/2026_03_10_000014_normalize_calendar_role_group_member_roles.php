<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class NormalizeCalendarRoleGroupMemberRoles extends Migration
{
    public function up()
    {
        if (!DB::getSchemaBuilder()->hasTable('calendar_role_group_members')) {
            return;
        }

        // Convertir roles antiguos a esquema nuevo (solo JEFE o MIEMBRO)
        DB::table('calendar_role_group_members')
            ->whereIn('role_type', ['COORDINACION', 'DOCUMENTACION', 'OTRO'])
            ->update(['role_type' => 'MIEMBRO']);
    }

    public function down()
    {
        // No se puede restaurar con precisión los valores antiguos.
    }
}

