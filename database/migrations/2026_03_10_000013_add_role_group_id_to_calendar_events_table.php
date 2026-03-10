<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('calendar_events')) {
            return;
        }

        Schema::table('calendar_events', function (Blueprint $table) {
            if (!Schema::hasColumn('calendar_events', 'role_group_id')) {
                $table->unsignedBigInteger('role_group_id')->nullable()->after('calendar_id');
                $table->index('role_group_id');
            }
        });

        // Asignar grupo por defecto a eventos existentes si existe el grupo CAL_IMPORTACIONES_DEFAULT
        try {
            $defaultGroupId = DB::table('calendar_role_groups')
                ->where('code', 'CAL_IMPORTACIONES_DEFAULT')
                ->value('id');

            if ($defaultGroupId) {
                DB::table('calendar_events')
                    ->whereNull('role_group_id')
                    ->update(['role_group_id' => $defaultGroupId]);
            }
        } catch (\Exception $e) {
            // En caso de error de tabla inexistente o similar, no interrumpir la migración
        }

        // Agregar restricción de clave foránea solo si existen ambas tablas y la columna
        Schema::table('calendar_events', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_events', 'role_group_id') && Schema::hasTable('calendar_role_groups')) {
                $table->foreign('role_group_id')
                    ->references('id')
                    ->on('calendar_role_groups')
                    ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('calendar_events')) {
            return;
        }

        Schema::table('calendar_events', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_events', 'role_group_id')) {
                $table->dropForeign(['role_group_id']);
                $table->dropColumn('role_group_id');
            }
        });
    }
};

