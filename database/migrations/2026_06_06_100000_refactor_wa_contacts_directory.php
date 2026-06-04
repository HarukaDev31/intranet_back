<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('wa_copiloto_inbox_contacts') && !Schema::hasTable('wa_contacts')) {
            Schema::rename('wa_copiloto_inbox_contacts', 'wa_contacts');
        }

        if (!Schema::hasTable('wa_contacts')) {
            Schema::create('wa_contacts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('phone_e164', 20);
                $table->string('contact_name', 255)->nullable();
                $table->string('source', 32)->default('inbox_coordinacion');
                $table->unsignedBigInteger('wa_inbox_conversation_id')->nullable()->index();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique('phone_e164', 'wa_contacts_phone_uq');
            });

            return;
        }

        if (Schema::hasColumn('wa_contacts', 'copiloto_session_id')) {
            $this->dedupeContactsByPhone();

            Schema::table('wa_contacts', function (Blueprint $table) {
                $table->dropForeign('wa_copiloto_inbox_contacts_copiloto_session_id_foreign');
            });

            Schema::table('wa_contacts', function (Blueprint $table) {
                $table->dropUnique('wa_copiloto_inbox_contacts_session_phone_uq');
                $table->dropColumn(['copiloto_session_id', 'copiloto_conversation_id']);
            });
        }

        if (!Schema::hasColumn('wa_contacts', 'source')) {
            Schema::table('wa_contacts', function (Blueprint $table) {
                $table->string('source', 32)->default('inbox_coordinacion')->after('contact_name');
            });
        }

        if (!$this->indexExists('wa_contacts', 'wa_contacts_phone_uq')) {
            Schema::table('wa_contacts', function (Blueprint $table) {
                $table->unique('phone_e164', 'wa_contacts_phone_uq');
            });
        }

        if (!Schema::hasColumn('wa_copiloto_conversations', 'contact_id')) {
            Schema::table('wa_copiloto_conversations', function (Blueprint $table) {
                $table->unsignedBigInteger('contact_id')->nullable()->index()->after('session_id');
            });
        }

        $this->backfillConversationContactIds();
    }

    public function down()
    {
        if (Schema::hasColumn('wa_copiloto_conversations', 'contact_id')) {
            Schema::table('wa_copiloto_conversations', function (Blueprint $table) {
                $table->dropColumn('contact_id');
            });
        }

        if (!Schema::hasTable('wa_contacts')) {
            return;
        }

        if ($this->indexExists('wa_contacts', 'wa_contacts_phone_uq')) {
            Schema::table('wa_contacts', function (Blueprint $table) {
                $table->dropUnique('wa_contacts_phone_uq');
            });
        }

        if (Schema::hasColumn('wa_contacts', 'source')) {
            Schema::table('wa_contacts', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }

        if (!Schema::hasColumn('wa_contacts', 'copiloto_session_id')) {
            Schema::table('wa_contacts', function (Blueprint $table) {
                $table->unsignedBigInteger('copiloto_session_id')->nullable()->index();
                $table->unsignedBigInteger('copiloto_conversation_id')->nullable()->index();
            });
        }

        if (!Schema::hasTable('wa_copiloto_inbox_contacts')) {
            Schema::rename('wa_contacts', 'wa_copiloto_inbox_contacts');
        }
    }

    /**
     * Conserva un registro por teléfono (el más reciente).
     */
    private function dedupeContactsByPhone()
    {
        $rows = DB::table('wa_contacts')
            ->select('phone_e164', DB::raw('COUNT(*) as c'))
            ->groupBy('phone_e164')
            ->having('c', '>', 1)
            ->get();

        foreach ($rows as $row) {
            $phone = (string) $row->phone_e164;
            $keepId = DB::table('wa_contacts')
                ->where('phone_e164', $phone)
                ->orderByDesc('synced_at')
                ->orderByDesc('id')
                ->value('id');

            if (!$keepId) {
                continue;
            }

            DB::table('wa_contacts')
                ->where('phone_e164', $phone)
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }

    private function backfillConversationContactIds()
    {
        if (!Schema::hasTable('wa_contacts') || !Schema::hasColumn('wa_copiloto_conversations', 'contact_id')) {
            return;
        }

        $contacts = DB::table('wa_contacts')->get(['id', 'phone_e164']);
        foreach ($contacts as $contact) {
            DB::table('wa_copiloto_conversations')
                ->where('phone_e164', $contact->phone_e164)
                ->whereNull('contact_id')
                ->update(['contact_id' => (int) $contact->id]);
        }
    }

    /**
     * @param  string  $table
     * @param  string  $index
     * @return bool
     */
    private function indexExists($table, $index)
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $db = $connection->getDatabaseName();
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
                [$db, $table, $index]
            );

            return $row && (int) $row->c > 0;
        }

        return false;
    }
};
