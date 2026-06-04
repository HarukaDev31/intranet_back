<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('wa_contacts')) {
            return;
        }

        Schema::table('wa_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_contacts', 'origin_module')) {
                $table->string('origin_module', 16)->nullable()->after('source');
            }
            if (!Schema::hasColumn('wa_contacts', 'origin_session_id')) {
                $table->unsignedBigInteger('origin_session_id')->nullable()->index()->after('origin_module');
            }
            if (!Schema::hasColumn('wa_contacts', 'origin_line_number')) {
                $table->string('origin_line_number', 32)->nullable()->after('origin_session_id');
            }
            if (!Schema::hasColumn('wa_contacts', 'origin_line_label')) {
                $table->string('origin_line_label', 64)->nullable()->after('origin_line_number');
            }
        });

        $this->backfillOriginFromInbox();
    }

    public function down()
    {
        if (!Schema::hasTable('wa_contacts')) {
            return;
        }

        Schema::table('wa_contacts', function (Blueprint $table) {
            $cols = ['origin_line_label', 'origin_line_number', 'origin_session_id', 'origin_module'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('wa_contacts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function backfillOriginFromInbox()
    {
        if (!Schema::hasTable('wa_inbox_conversations') || !Schema::hasTable('wa_inbox_sessions')) {
            return;
        }

        $rows = DB::table('wa_contacts')
            ->whereNull('origin_line_number')
            ->whereNotNull('wa_inbox_conversation_id')
            ->get(['id', 'wa_inbox_conversation_id']);

        foreach ($rows as $row) {
            $conv = DB::table('wa_inbox_conversations')
                ->where('id', (int) $row->wa_inbox_conversation_id)
                ->first(['session_id']);

            if (!$conv || !$conv->session_id) {
                continue;
            }

            $session = DB::table('wa_inbox_sessions')
                ->where('id', (int) $conv->session_id)
                ->first(['id', 'display_number', 'label']);

            if (!$session) {
                continue;
            }

            DB::table('wa_contacts')
                ->where('id', (int) $row->id)
                ->update([
                    'origin_module' => 'inbox',
                    'origin_session_id' => (int) $session->id,
                    'origin_line_number' => (string) ($session->display_number ?: ''),
                    'origin_line_label' => (string) ($session->label ?: 'Coordinación'),
                ]);
        }

        $defaultInbox = DB::table('wa_inbox_sessions')->orderBy('id')->first(['id', 'display_number', 'label']);
        if (!$defaultInbox) {
            return;
        }

        DB::table('wa_contacts')
            ->whereNull('origin_line_number')
            ->where('source', 'inbox_coordinacion')
            ->update([
                'origin_module' => 'inbox',
                'origin_session_id' => (int) $defaultInbox->id,
                'origin_line_number' => (string) ($defaultInbox->display_number ?: ''),
                'origin_line_label' => (string) ($defaultInbox->label ?: 'Coordinación'),
            ]);
    }
};
