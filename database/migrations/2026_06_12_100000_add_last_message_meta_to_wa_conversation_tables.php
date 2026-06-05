<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asegura columnas de último mensaje en conversaciones inbox/copiloto
     * (instalaciones donde la tabla ya existía antes de añadirlas al create).
     */
    public function up()
    {
        $this->addLastMessageMetaColumns('wa_inbox_conversations');
        $this->addLastMessageMetaColumns('wa_copiloto_conversations');
    }

    public function down()
    {
        $this->dropLastMessageMetaColumns('wa_inbox_conversations');
        $this->dropLastMessageMetaColumns('wa_copiloto_conversations');
    }

    /**
     * @param  string  $table
     */
    private function addLastMessageMetaColumns($table)
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (!Schema::hasColumn($table, 'last_message_id')) {
                $blueprint->unsignedBigInteger('last_message_id')->nullable()->after('last_direction');
            }
            if (!Schema::hasColumn($table, 'last_message_type')) {
                $blueprint->string('last_message_type', 32)->nullable()->after('last_message_id');
            }
            if (!Schema::hasColumn($table, 'last_message_delivery_status')) {
                $blueprint->string('last_message_delivery_status', 32)->nullable()->after('last_message_type');
            }
        });
    }

    /**
     * @param  string  $table
     */
    private function dropLastMessageMetaColumns($table)
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            foreach (['last_message_delivery_status', 'last_message_type', 'last_message_id'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }
};
