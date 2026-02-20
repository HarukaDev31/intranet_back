<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_activities', function (Blueprint $table) {
            $table->string('color_code', 20)->nullable()->after('orden');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_activities', function (Blueprint $table) {
            $table->dropColumn('color_code');
        });
    }
};
