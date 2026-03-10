<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColorPriorityOrderToCalendarRoleGroupConfigs extends Migration
{
    public function up()
    {
        Schema::table('calendar_role_group_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('calendar_role_group_configs', 'jefe_color_priority_order')) {
                $table->string('jefe_color_priority_order', 100)->nullable()->after('color_completado');
            }
            if (!Schema::hasColumn('calendar_role_group_configs', 'miembro_color_priority_order')) {
                $table->string('miembro_color_priority_order', 100)->nullable()->after('jefe_color_priority_order');
            }
        });
    }

    public function down()
    {
        Schema::table('calendar_role_group_configs', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_role_group_configs', 'jefe_color_priority_order')) {
                $table->dropColumn('jefe_color_priority_order');
            }
            if (Schema::hasColumn('calendar_role_group_configs', 'miembro_color_priority_order')) {
                $table->dropColumn('miembro_color_priority_order');
            }
        });
    }
}

