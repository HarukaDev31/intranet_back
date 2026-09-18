<?php

use App\Support\WhatsApp\WaInboxSecretCrypt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EncryptWaInboxOrganizacionSecrets extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('wa_inbox_organizacion_config')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE wa_inbox_organizacion_config MODIFY app_secret TEXT NULL');
            DB::statement('ALTER TABLE wa_inbox_organizacion_config MODIFY webhook_verify_token TEXT NULL');
        }

        $rows = DB::table('wa_inbox_organizacion_config')->get();
        foreach ($rows as $row) {
            $updates = [];
            foreach (['access_token', 'app_secret', 'webhook_verify_token'] as $column) {
                $value = isset($row->{$column}) ? (string) $row->{$column} : '';
                if ($value === '' || WaInboxSecretCrypt::looksEncrypted($value)) {
                    continue;
                }
                $updates[$column] = WaInboxSecretCrypt::encrypt($value);
            }
            if ($updates !== []) {
                DB::table('wa_inbox_organizacion_config')
                    ->where('id', $row->id)
                    ->update($updates);
            }
        }
    }

    public function down()
    {
        if (!Schema::hasTable('wa_inbox_organizacion_config')) {
            return;
        }

        $rows = DB::table('wa_inbox_organizacion_config')->get();
        foreach ($rows as $row) {
            $updates = [];
            foreach (['access_token', 'app_secret', 'webhook_verify_token'] as $column) {
                $value = isset($row->{$column}) ? (string) $row->{$column} : '';
                if ($value === '' || !WaInboxSecretCrypt::looksEncrypted($value)) {
                    continue;
                }
                $updates[$column] = WaInboxSecretCrypt::decrypt($value);
            }
            if ($updates !== []) {
                DB::table('wa_inbox_organizacion_config')
                    ->where('id', $row->id)
                    ->update($updates);
            }
        }
    }
}
