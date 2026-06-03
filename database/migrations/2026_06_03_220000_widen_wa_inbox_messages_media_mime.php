<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WidenWaInboxMessagesMediaMime extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('wa_inbox_messages')) {
            return;
        }

        DB::statement('ALTER TABLE wa_inbox_messages MODIFY media_mime VARCHAR(128) NULL');
    }

    public function down()
    {
        if (!Schema::hasTable('wa_inbox_messages')) {
            return;
        }

        DB::statement('ALTER TABLE wa_inbox_messages MODIFY media_mime VARCHAR(64) NULL');
    }
}
