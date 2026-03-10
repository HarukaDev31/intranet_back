<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_role_group_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('calendar_role_group_configs', 'show_event_details')) {
                $table->boolean('show_event_details')->default(false)->after('miembro_color_priority_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('calendar_role_group_configs', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_role_group_configs', 'show_event_details')) {
                $table->dropColumn('show_event_details');
            }
        });
    }
};
