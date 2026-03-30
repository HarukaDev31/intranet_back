<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_consolidado_leads', function (Blueprint $table) {
            $table->string('codigo_campana', 32)->nullable()->after('proveedor');
        });
    }

    public function down(): void
    {
        Schema::table('landing_consolidado_leads', function (Blueprint $table) {
            $table->dropColumn('codigo_campana');
        });
    }
};
