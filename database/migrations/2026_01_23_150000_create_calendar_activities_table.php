<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalendarActivitiesTable extends Migration
{
    public function up()
    {
        Schema::create('calendar_activities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->index('name');
        });
    }

    public function down()
    {
        Schema::dropIfExists('calendar_activities');
    }
}
