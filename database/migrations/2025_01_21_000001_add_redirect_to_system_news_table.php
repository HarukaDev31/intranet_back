<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRedirectToSystemNewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('system_news', function (Blueprint $table) {
            $table->string('redirect')->nullable()->after('published_at');
            $table->index('redirect');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('system_news', function (Blueprint $table) {
            $table->dropIndex(['redirect']);
            $table->dropColumn('redirect');
        });
    }
}

