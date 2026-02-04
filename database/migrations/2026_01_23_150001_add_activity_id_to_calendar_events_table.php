<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddActivityIdToCalendarEventsTable extends Migration
{
    public function up()
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->unsignedBigInteger('activity_id')->nullable()->after('calendar_id');
            $table->foreign('activity_id')->references('id')->on('calendar_activities')->onDelete('set null');
            $table->index('activity_id');
        });
    }

    public function down()
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
            $table->dropColumn('activity_id');
        });
    }
}
