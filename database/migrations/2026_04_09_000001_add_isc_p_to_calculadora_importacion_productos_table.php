<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculadora_importacion_productos', function (Blueprint $table) {
            $table->decimal('isc_p', 20, 10)->default(0)->after('ad_valorem_p');
        });
    }

    public function down(): void
    {
        Schema::table('calculadora_importacion_productos', function (Blueprint $table) {
            $table->dropColumn('isc_p');
        });
    }
};
