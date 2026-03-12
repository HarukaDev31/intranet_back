<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalendarEventSubtasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calendar_event_subtasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('calendar_event_charge_id');
            $table->string('name', 255);
            $table->integer('duration_hours')->default(0);
            $table->string('status', 20)->default('PENDIENTE');
            $table->timestamps();

            $table->index('calendar_event_charge_id', 'idx_calendar_event_subtasks_charge_id');
            $table->foreign('calendar_event_charge_id')
                ->references('id')
                ->on('calendar_event_charges')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calendar_event_subtasks');
    }
}

