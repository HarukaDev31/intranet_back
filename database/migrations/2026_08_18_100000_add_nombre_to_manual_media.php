<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNombreToManualMedia extends Migration
{
    public function up()
    {
        Schema::table('manual_media', function (Blueprint $table) {
            $table->string('nombre', 200)->nullable()->after('path');
        });
    }

    public function down()
    {
        Schema::table('manual_media', function (Blueprint $table) {
            $table->dropColumn('nombre');
        });
    }
}
