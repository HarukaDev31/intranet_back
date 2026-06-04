<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('wa_copiloto_sessions')) {
            return;
        }

        Schema::table('wa_copiloto_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_copiloto_sessions', 'waba_id')) {
                $table->string('waba_id', 32)->nullable()->after('phone_number_id');
            }
            if (!Schema::hasColumn('wa_copiloto_sessions', 'template_name_prefix')) {
                $table->string('template_name_prefix', 64)->nullable()->after('label');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('wa_copiloto_sessions')) {
            return;
        }

        Schema::table('wa_copiloto_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('wa_copiloto_sessions', 'template_name_prefix')) {
                $table->dropColumn('template_name_prefix');
            }
            if (Schema::hasColumn('wa_copiloto_sessions', 'waba_id')) {
                $table->dropColumn('waba_id');
            }
        });
    }
};
