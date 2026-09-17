<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'whatsapp_prefix')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_prefix', 8)->nullable()->after('whatsapp');
        });
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'whatsapp_prefix')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('whatsapp_prefix');
            });
        }
    }
};
