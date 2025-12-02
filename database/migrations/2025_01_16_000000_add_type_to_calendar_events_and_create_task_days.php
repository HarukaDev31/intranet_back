<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeToCalendarEventsAndCreateTaskDays extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Agregar campo type a calendar_events
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->enum('type', ['evento', 'tarea'])->default('evento')->after('id');
            $table->unsignedBigInteger('parent_task_id')->nullable()->after('type');
            $table->index('type');
            $table->index('parent_task_id');
            
            // Foreign key para parent_task_id
            $table->foreign('parent_task_id')->references('id')->on('calendar_events')->onDelete('cascade');
        });

        // Crear tabla para días de tareas
        Schema::create('calendar_task_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->date('day_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_all_day')->default(true);
            $table->string('color')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('task_id')->references('id')->on('calendar_events')->onDelete('cascade');

            // Indexes
            $table->index('task_id');
            $table->index('day_date');
            $table->unique(['task_id', 'day_date']); // Un día solo puede aparecer una vez por tarea
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calendar_task_days');
        
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropForeign(['parent_task_id']);
            $table->dropIndex(['type']);
            $table->dropIndex(['parent_task_id']);
            $table->dropColumn(['type', 'parent_task_id']);
        });
    }
}

