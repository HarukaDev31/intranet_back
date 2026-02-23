<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddScheduleOptionsToCalendarActivitiesTable extends Migration
{
    public function up()
    {
        Schema::table('calendar_activities', function (Blueprint $table) {
            $table->boolean('allow_saturday')->default(false)->after('color_code');
            $table->boolean('allow_sunday')->default(false)->after('allow_saturday');
            $table->tinyInteger('default_priority')->default(0)->after('allow_sunday');
        });
    }

    public function down()
    {
        Schema::table('calendar_activities', function (Blueprint $table) {
            $table->dropColumn(['allow_saturday', 'allow_sunday', 'default_priority']);
        });
    }
}
