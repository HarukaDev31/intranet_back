<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_curso_planes', function (Blueprint $table) {
            $table->dropUnique(['page_key', 'tipo_pago']);
            $table->unique(['page_key', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('web_curso_planes', function (Blueprint $table) {
            $table->dropUnique(['page_key', 'sort_order']);
            $table->unique(['page_key', 'tipo_pago']);
        });
    }
};
