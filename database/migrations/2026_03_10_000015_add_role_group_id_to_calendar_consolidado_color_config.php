<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('calendar_consolidado_color_config')) {
            return;
        }

        Schema::table('calendar_consolidado_color_config', function (Blueprint $table) {
            if (!Schema::hasColumn('calendar_consolidado_color_config', 'role_group_id')) {
                $table->unsignedBigInteger('role_group_id')->nullable()->after('calendar_id');
                $table->index('role_group_id', 'cal_consol_color_role_group_idx');
            }
        });

        // Asignar grupo por defecto a los registros existentes (grupo de calendario por defecto)
        try {
            $defaultGroupId = DB::table('calendar_role_groups')
                ->where('code', 'CAL_IMPORTACIONES_DEFAULT')
                ->value('id');

            if ($defaultGroupId) {
                DB::table('calendar_consolidado_color_config')
                    ->whereNull('role_group_id')
                    ->update(['role_group_id' => $defaultGroupId]);
            }
        } catch (\Exception $e) {
            // No interrumpir la migración si falta alguna tabla
        }

        // Agregar la FK si existe la tabla de grupos
        Schema::table('calendar_consolidado_color_config', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_consolidado_color_config', 'role_group_id')
                && Schema::hasTable('calendar_role_groups')) {
                $table->foreign('role_group_id')
                    ->references('id')
                    ->on('calendar_role_groups')
                    ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('calendar_consolidado_color_config')) {
            return;
        }

        Schema::table('calendar_consolidado_color_config', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_consolidado_color_config', 'role_group_id')) {
                $table->dropForeign(['role_group_id']);
                $table->dropColumn('role_group_id');
            }
        });
    }
};

