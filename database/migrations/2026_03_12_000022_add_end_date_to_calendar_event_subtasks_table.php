<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEndDateToCalendarEventSubtasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('calendar_event_subtasks', function (Blueprint $table) {
            if (!Schema::hasColumn('calendar_event_subtasks', 'end_date')) {
                $table->date('end_date')->nullable()->after('duration_hours');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('calendar_event_subtasks', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_event_subtasks', 'end_date')) {
                $table->dropColumn('end_date');
            }
        });
    }
}

