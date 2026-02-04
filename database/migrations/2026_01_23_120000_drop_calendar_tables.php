<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina todas las tablas asociadas al módulo de Calendario.
 *
 * Tablas eliminadas:
 * - calendar_task_days (FK a calendar_events, debe eliminarse primero)
 * - calendar_events
 *
 * Archivos asociados que deberían eliminarse manualmente si se desea quitar el módulo:
 * - app/Http/Controllers/Calendar/CalendarController.php
 * - app/Models/Calendar/Evento.php
 * - app/Models/Calendar/TaskDay.php
 * - routes/modules/calendar.php (y su require en api.php)
 */
class DropCalendarTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('calendar_task_days');
        Schema::dropIfExists('calendar_events');
    }

    /**
     * Reverse the migrations.
     * No se puede revertir automáticamente. Para restaurar el calendario,
     * ejecutar las migraciones originales: create_calendar_events_table y
     * add_type_to_calendar_events_and_create_task_days.
     *
     * @return void
     */
    public function down()
    {
        // No revertir: migración destructiva
    }
}
