<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLastMessageMetaToWaInboxConversations extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('wa_inbox_conversations')) {
            return;
        }

        Schema::table('wa_inbox_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_inbox_conversations', 'last_message_id')) {
                $table->unsignedInteger('last_message_id')->nullable()->after('last_message_at');
            }
            if (!Schema::hasColumn('wa_inbox_conversations', 'last_message_type')) {
                $table->string('last_message_type', 32)->nullable()->after('last_message_id');
            }
            if (!Schema::hasColumn('wa_inbox_conversations', 'last_message_delivery_status')) {
                $table->string('last_message_delivery_status', 32)->nullable()->after('last_message_type');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('wa_inbox_conversations')) {
            return;
        }

        Schema::table('wa_inbox_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('wa_inbox_conversations', 'last_message_delivery_status')) {
                $table->dropColumn('last_message_delivery_status');
            }
            if (Schema::hasColumn('wa_inbox_conversations', 'last_message_type')) {
                $table->dropColumn('last_message_type');
            }
            if (Schema::hasColumn('wa_inbox_conversations', 'last_message_id')) {
                $table->dropColumn('last_message_id');
            }
        });
    }
}
