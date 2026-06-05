<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('wa_copiloto_conversations')) {
            return;
        }

        Schema::table('wa_copiloto_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_copiloto_conversations', 'ai_context_summary')) {
                $table->text('ai_context_summary')->nullable()->after('status');
            }
            if (!Schema::hasColumn('wa_copiloto_conversations', 'ai_summary_through_message_id')) {
                $table->unsignedBigInteger('ai_summary_through_message_id')->nullable()->after('ai_context_summary');
            }
            if (!Schema::hasColumn('wa_copiloto_conversations', 'ai_temperatura')) {
                $table->unsignedTinyInteger('ai_temperatura')->nullable()->after('ai_summary_through_message_id');
            }
            if (!Schema::hasColumn('wa_copiloto_conversations', 'ai_temperatura_at')) {
                $table->timestamp('ai_temperatura_at')->nullable()->after('ai_temperatura');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('wa_copiloto_conversations')) {
            return;
        }

        Schema::table('wa_copiloto_conversations', function (Blueprint $table) {
            foreach (['ai_temperatura_at', 'ai_temperatura', 'ai_summary_through_message_id', 'ai_context_summary'] as $col) {
                if (Schema::hasColumn('wa_copiloto_conversations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
