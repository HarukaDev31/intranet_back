<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WidenFailedReasonColumnsOnWhatsappTables extends Migration
{
    public function up()
    {
        $this->widenColumn('wa_inbox_messages');
        $this->widenColumn('wa_copiloto_messages');
        $this->widenColumn('wa_copiloto_scheduled_messages');
    }

    public function down()
    {
        $this->narrowColumn('wa_inbox_messages', 255);
        $this->narrowColumn('wa_copiloto_messages', 255);
        $this->narrowColumn('wa_copiloto_scheduled_messages', 500);
    }

    /**
     * @param  string  $table
     */
    private function widenColumn($table)
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'failed_reason')) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` MODIFY `failed_reason` TEXT NULL");
    }

    /**
     * @param  string  $table
     * @param  int  $length
     */
    private function narrowColumn($table, $length)
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'failed_reason')) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` MODIFY `failed_reason` VARCHAR({$length}) NULL");
    }
}
