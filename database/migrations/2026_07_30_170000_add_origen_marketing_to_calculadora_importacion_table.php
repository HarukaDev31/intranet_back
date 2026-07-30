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
            if (!Schema::hasColumn('calculadora_importacion', 'origen_marketing')) {
                $table->string('origen_marketing', 100)->nullable()->after('tipo_cliente');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            if (Schema::hasColumn('calculadora_importacion', 'origen_marketing')) {
                $table->dropColumn('origen_marketing');
            }
        });
    }
};
