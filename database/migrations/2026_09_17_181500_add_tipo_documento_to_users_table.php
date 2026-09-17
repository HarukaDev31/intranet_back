<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'tipo_documento')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('tipo_documento', 10)->nullable()->default('DNI')->after('dni');
        });
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'tipo_documento')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('tipo_documento');
            });
        }
    }
};
