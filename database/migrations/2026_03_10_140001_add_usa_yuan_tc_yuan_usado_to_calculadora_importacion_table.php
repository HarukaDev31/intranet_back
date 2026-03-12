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
            if (!Schema::hasColumn('calculadora_importacion', 'usa_yuan')) {
                $table->boolean('usa_yuan')->default(false)->after('es_imo');
            }
            if (!Schema::hasColumn('calculadora_importacion', 'tc_yuan_usado')) {
                $table->decimal('tc_yuan_usado', 18, 8)->nullable()->after('usa_yuan');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            if (Schema::hasColumn('calculadora_importacion', 'usa_yuan')) {
                $table->dropColumn('usa_yuan');
            }
            if (Schema::hasColumn('calculadora_importacion', 'tc_yuan_usado')) {
                $table->dropColumn('tc_yuan_usado');
            }
        });
    }
};
