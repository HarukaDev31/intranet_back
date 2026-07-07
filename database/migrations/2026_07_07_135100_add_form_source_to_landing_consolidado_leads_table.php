<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_consolidado_leads', function (Blueprint $table) {
            $table->string('form_source', 64)
                ->default('probusiness_pe')
                ->after('codigo_campana');
        });
    }

    public function down(): void
    {
        Schema::table('landing_consolidado_leads', function (Blueprint $table) {
            $table->dropColumn('form_source');
        });
    }
};
