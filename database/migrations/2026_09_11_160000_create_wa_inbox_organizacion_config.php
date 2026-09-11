<?php

use App\Support\WhatsApp\WaInboxSecretCrypt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateWaInboxOrganizacionConfig extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('wa_inbox_organizacion_config')) {
            Schema::create('wa_inbox_organizacion_config', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('organizacion_id')->unique();
                $table->boolean('enabled')->default(false);
                $table->text('access_token')->nullable();
                $table->string('phone_number_id', 32)->nullable();
                $table->text('app_secret')->nullable();
                $table->text('webhook_verify_token')->nullable();
                $table->string('waba_id', 32)->nullable();
                $table->string('graph_api_version', 16)->default('v19.0');
                $table->string('default_language', 16)->default('es_PE');
                $table->string('display_number', 32)->nullable();
                $table->boolean('legacy_fallback')->default(true);
                $table->boolean('preview_from_template')->default(true);
                $table->boolean('session_when_window_open')->default(true);
                $table->timestamps();

                $table->index('phone_number_id', 'wa_inbox_org_cfg_phone_idx');
            });
        }

        if (Schema::hasTable('wa_inbox_sessions') && !Schema::hasColumn('wa_inbox_sessions', 'organizacion_id')) {
            Schema::table('wa_inbox_sessions', function (Blueprint $table) {
                $table->unsignedInteger('organizacion_id')->nullable()->after('id');
            });
            DB::table('wa_inbox_sessions')->whereNull('organizacion_id')->update(['organizacion_id' => 1]);
            Schema::table('wa_inbox_sessions', function (Blueprint $table) {
                $table->index('organizacion_id', 'wa_inbox_sessions_org_idx');
            });
        }

        $this->seedOrganizacionUnoDesdeEnv();
    }

    public function down()
    {
        if (Schema::hasTable('wa_inbox_sessions') && Schema::hasColumn('wa_inbox_sessions', 'organizacion_id')) {
            Schema::table('wa_inbox_sessions', function (Blueprint $table) {
                $table->dropIndex('wa_inbox_sessions_org_idx');
                $table->dropColumn('organizacion_id');
            });
        }

        Schema::dropIfExists('wa_inbox_organizacion_config');
    }

    private function seedOrganizacionUnoDesdeEnv()
    {
        if (DB::table('wa_inbox_organizacion_config')->where('organizacion_id', 1)->exists()) {
            return;
        }

        $phone = (string) env('META_WHATSAPP_PHONE_NUMBER_ID', '');
        $token = (string) env('META_WHATSAPP_ACCESS_TOKEN', '');
        $enabled = filter_var(env('META_WHATSAPP_COORDINACION_ENABLED', false), FILTER_VALIDATE_BOOLEAN);

        DB::table('wa_inbox_organizacion_config')->insert([
            'organizacion_id' => 1,
            'enabled' => $enabled && $phone !== '' && $token !== '',
            'access_token' => WaInboxSecretCrypt::encrypt($token),
            'phone_number_id' => $phone !== '' ? $phone : null,
            'app_secret' => WaInboxSecretCrypt::encrypt($this->nullableEnv('META_WHATSAPP_APP_SECRET')),
            'webhook_verify_token' => WaInboxSecretCrypt::encrypt($this->nullableEnv('META_WHATSAPP_WEBHOOK_VERIFY_TOKEN')),
            'waba_id' => $this->nullableEnv('META_WHATSAPP_WABA_ID'),
            'graph_api_version' => env('META_WHATSAPP_GRAPH_VERSION', 'v19.0'),
            'default_language' => env('META_WHATSAPP_LANGUAGE', 'es_PE'),
            'display_number' => $this->nullableEnv('META_WHATSAPP_INBOX_DISPLAY_NUMBER'),
            'legacy_fallback' => filter_var(env('META_WHATSAPP_LEGACY_FALLBACK', true), FILTER_VALIDATE_BOOLEAN),
            'preview_from_template' => filter_var(env('META_WHATSAPP_COORDINACION_PREVIEW_FROM_TEMPLATE', true), FILTER_VALIDATE_BOOLEAN),
            'session_when_window_open' => filter_var(env('META_WHATSAPP_SESSION_WHEN_WINDOW_OPEN', true), FILTER_VALIDATE_BOOLEAN),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($phone !== '' && Schema::hasTable('wa_inbox_sessions')) {
            $session = DB::table('wa_inbox_sessions')->where('phone_number_id', $phone)->first();
            if ($session) {
                DB::table('wa_inbox_sessions')->where('id', $session->id)->update([
                    'organizacion_id' => 1,
                    'display_number' => env('META_WHATSAPP_INBOX_DISPLAY_NUMBER', $session->display_number),
                    'is_active' => $enabled ? 1 : 0,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * @param  string  $key
     * @return string|null
     */
    private function nullableEnv($key)
    {
        $value = trim((string) env($key, ''));

        return $value !== '' ? $value : null;
    }
}
