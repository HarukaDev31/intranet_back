<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrdenToCalendarActivitiesTable extends Migration
{
    public function up()
    {
        Schema::table('calendar_activities', function (Blueprint $table) {
            $table->integer('orden')->default(0)->after('name');
            $table->index('orden');
        });
    }

    public function down()
    {
        Schema::table('calendar_activities', function (Blueprint $table) {
            $table->dropIndex(['orden']);
            $table->dropColumn('orden');
        });
    }
}
