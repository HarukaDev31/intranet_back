<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       
    }

    public function down(): void
    {
        Schema::table('landing_curso_leads', function (Blueprint $table) {
            $table->dropColumn('codigo_campana');
        });
    }
};
