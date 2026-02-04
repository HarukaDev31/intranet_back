<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalendarEventChargeTrackingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calendar_event_charge_tracking', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('calendar_event_charge_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->timestamp('changed_at');
            $table->unsignedInteger('changed_by')->nullable();

            $table->foreign('calendar_event_charge_id')->references('id')->on('calendar_event_charges')->onDelete('cascade');
            $table->foreign('changed_by')->references('ID_Usuario')->on('usuario')->onDelete('set null');
            $table->index('calendar_event_charge_id');
            $table->index('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calendar_event_charge_tracking');
    }
}
