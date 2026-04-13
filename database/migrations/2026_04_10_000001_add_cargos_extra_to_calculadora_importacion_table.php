<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->decimal('cargos_extra', 15, 2)->nullable()->after('logistica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->dropColumn('cargos_extra');
        });
    }
};
