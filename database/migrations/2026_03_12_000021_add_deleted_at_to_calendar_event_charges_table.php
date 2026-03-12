<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeletedAtToCalendarEventChargesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('calendar_event_charges', function (Blueprint $table) {
            if (!Schema::hasColumn('calendar_event_charges', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
                $table->index('deleted_at', 'idx_calendar_event_charges_deleted_at');
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
        Schema::table('calendar_event_charges', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_event_charges', 'deleted_at')) {
                $table->dropIndex('idx_calendar_event_charges_deleted_at');
                $table->dropColumn('deleted_at');
            }
        });
    }
}

