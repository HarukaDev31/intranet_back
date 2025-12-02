<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCalendarEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('calendar_events');
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_all_day')->default(true);
            $table->boolean('is_for_me')->default(false);
            $table->unsignedInteger('role_id')->nullable();
            $table->string('role_name')->nullable();
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('created_by');
            $table->string('created_by_name')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('created_by')->references('ID_Usuario')->on('usuario')->onDelete('cascade');
            $table->foreign('role_id')->references('ID_Grupo')->on('grupo')->onDelete('set null');

            // Indexes
            $table->index('start_date');
            $table->index('end_date');
            $table->index('created_by');
            $table->index('role_id');
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calendar_events');
    }
}

