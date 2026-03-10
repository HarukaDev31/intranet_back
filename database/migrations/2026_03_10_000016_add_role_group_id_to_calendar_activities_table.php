<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Añade role_group_id a calendar_activities para separar el catálogo por grupo.
     * Las actividades existentes se asignan al grupo con id 1 (o al primer grupo si no existe id 1).
     */
    public function up(): void
    {
        if (!Schema::hasTable('calendar_activities')) {
            return;
        }

        Schema::table('calendar_activities', function (Blueprint $table) {
            if (!Schema::hasColumn('calendar_activities', 'role_group_id')) {
                $table->unsignedBigInteger('role_group_id')->nullable()->after('id');
                $table->index('role_group_id');
            }
        });

        if (!Schema::hasTable('calendar_role_groups')) {
            return;
        }

        // Asignar grupo 1 a todas las actividades existentes; si no existe id 1, usar el primer grupo
        $defaultGroupId = DB::table('calendar_role_groups')->where('id', 1)->value('id');
        if (!$defaultGroupId) {
            $defaultGroupId = DB::table('calendar_role_groups')->orderBy('id')->value('id');
        }
        if ($defaultGroupId) {
            DB::table('calendar_activities')
                ->whereNull('role_group_id')
                ->update(['role_group_id' => $defaultGroupId]);
            Schema::table('calendar_activities', function (Blueprint $table) {
                $table->unsignedBigInteger('role_group_id')->nullable(false)->change();
            });
        }

        Schema::table('calendar_activities', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_activities', 'role_group_id') && Schema::hasTable('calendar_role_groups')) {
                $table->foreign('role_group_id')
                    ->references('id')
                    ->on('calendar_role_groups')
                    ->onDelete('restrict');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('calendar_activities')) {
            return;
        }

        Schema::table('calendar_activities', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_activities', 'role_group_id')) {
                $table->dropForeign(['role_group_id']);
                $table->dropColumn('role_group_id');
            }
        });
    }
};
